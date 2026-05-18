<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\booking_core\BookingSlotService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BookingForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected MailManagerInterface $mailManager;
  protected LockBackendInterface $lock;
  protected FloodInterface $flood;
  protected BookingSlotService $slotService;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
    LockBackendInterface $lock,
    FloodInterface $flood,
    BookingSlotService $slot_service,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager       = $mail_manager;
    $this->configFactory     = $config_factory;
    $this->lock              = $lock;
    $this->flood             = $flood;
    $this->slotService       = $slot_service;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('lock'),
      $container->get('flood'),
      $container->get('booking_core.slot_service'),
    );
  }

  public function getFormId(): string {
    return 'booking_core_booking_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config   = $this->configFactory->get('booking_core.settings');
    $services = $config->get('services') ?? [];
    $options  = array_combine($services, $services);

    $form['name'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Full name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Email address'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type'      => 'tel',
      '#title'     => $this->t('Phone number'),
      '#maxlength' => 50,
    ];

    $form['service'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Service'),
      '#options'      => $options,
      '#required'     => TRUE,
      '#empty_option' => $this->t('- Select a service -'),
    ];

    $form['date'] = [
      '#type'       => 'date',
      '#title'      => $this->t('Date'),
      '#required'   => TRUE,
      '#attributes' => ['min' => date('Y-m-d', strtotime('+1 day'))],
      '#ajax'       => [
        'callback'            => '::updateTimeSlots',
        'wrapper'             => 'time-wrapper',
        'event'               => 'change',
        'progress'            => ['type' => 'throbber', 'message' => NULL],
        'disable_refocus'     => TRUE,
      ],
    ];

    $input         = $form_state->getUserInput();
    $selected_date = $form_state->getValue('date') ?? ($input['date'] ?? NULL);
    $slots         = $selected_date ? $this->slotService->getAvailableSlots($selected_date) : [];

    $form['time'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Time'),
      '#options'      => $slots,
      '#required'     => TRUE,
      '#empty_option' => $selected_date
        ? ($slots ? $this->t('- Select a time -') : $this->t('No slots available for this date'))
        : $this->t('- Select a date first -'),
      '#prefix'       => '<div id="time-wrapper">',
      '#suffix'       => '</div>',
    ];

    $form['notes'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows'  => 4,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Book appointment'),
    ];

    return $form;
  }

  public function updateTimeSlots(array &$form, FormStateInterface $form_state): array {
    return $form['time'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ip = $this->getRequest()->getClientIp();
    if (!$this->flood->isAllowed('booking_core_submit', 5, 3600, $ip)) {
      $form_state->setErrorByName('', $this->t('Too many booking attempts. Please try again later.'));
      return;
    }

    $date = $form_state->getValue('date');
    $time = $form_state->getValue('time');

    if ($date) {
      $slots = $this->slotService->getAvailableSlots($date);
      if (empty($slots)) {
        $form_state->setErrorByName('date', $this->t('No appointments are available on this date.'));
      }
      elseif (!$time || !isset($slots[$time])) {
        $form_state->setErrorByName('time', $this->t('Please select an available time slot.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $date     = $form_state->getValue('date');
    $time     = $form_state->getValue('time');
    $iso      = $date . 'T' . $time . ':00';
    $lock_key = 'booking_core_slot_' . md5($iso);

    if (!$this->lock->acquire($lock_key, 15)) {
      $this->messenger()->addError($this->t('That slot is currently being booked. Please try again.'));
      return;
    }

    // Re-check availability inside the lock to prevent race conditions.
    $slots = $this->slotService->getAvailableSlots($date);
    if (!isset($slots[$time])) {
      $this->lock->release($lock_key);
      $this->messenger()->addError($this->t('That time slot was just taken. Please choose another.'));
      return;
    }

    try {
      $booking = $this->entityTypeManager->getStorage('booking')->create([
        'name'    => $form_state->getValue('name'),
        'email'   => $form_state->getValue('email'),
        'phone'   => $form_state->getValue('phone') ?? '',
        'service' => $form_state->getValue('service') ?? '',
        'date'    => $iso,
        'notes'   => $form_state->getValue('notes') ?? '',
      ]);
      $booking->save();
    }
    catch (\Exception $e) {
      $this->lock->release($lock_key);
      $this->messenger()->addError($this->t('Could not save your booking. Please try again.'));
      \Drupal::logger('booking_core')->error('Booking save failed: @msg', ['@msg' => $e->getMessage()]);
      return;
    }

    $this->lock->release($lock_key);

    $config      = $this->configFactory->get('booking_core.settings');
    $langcode    = $this->configFactory->get('system.site')->get('langcode');
    $params      = [
      'name'    => $form_state->getValue('name'),
      'date'    => $iso,
      'email'   => $form_state->getValue('email'),
      'phone'   => $form_state->getValue('phone') ?? '',
      'service' => $form_state->getValue('service') ?? '',
      'notes'   => $form_state->getValue('notes') ?? '',
    ];

    $this->mailManager->mail('booking_core', 'confirmation', $form_state->getValue('email'), $langcode, $params);

    $admin_email = $config->get('admin_email') ?: $this->configFactory->get('system.site')->get('mail');
    $this->mailManager->mail('booking_core', 'notification', $admin_email, $langcode, $params);

    $this->flood->register('booking_core_submit', 3600, $this->getRequest()->getClientIp());

    $form_state->setRedirectUrl(Url::fromRoute('booking_core.thank_you'));
  }

}
