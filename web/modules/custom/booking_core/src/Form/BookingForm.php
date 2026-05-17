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
use Symfony\Component\DependencyInjection\ContainerInterface;

class BookingForm extends FormBase {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private MailManagerInterface $mailManager,
    ConfigFactoryInterface $config_factory,
    private LockBackendInterface $lock,
    private FloodInterface $flood,
  ) {
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('lock'),
      $container->get('flood'),
    );
  }

  public function getFormId(): string {
    return 'booking_core_booking_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Full name'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
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

    $form['date'] = [
      '#type'     => 'datetime',
      '#title'    => $this->t('Appointment date and time'),
      '#required' => TRUE,
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

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ip = $this->getRequest()->getClientIp();

    if (!$this->flood->isAllowed('booking_core_submit', 5, 3600, $ip)) {
      $form_state->setErrorByName('', $this->t('Too many booking attempts. Please try again later.'));
    }

    $date = $form_state->getValue('date');
    if ($date && $date->getTimestamp() < \Drupal::time()->getRequestTime()) {
      $form_state->setErrorByName('date', $this->t('Please choose a future date and time.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $date  = $form_state->getValue('date');
    $iso   = $date->format('Y-m-d\TH:i:s');
    $lock_key = 'booking_core_slot_' . md5($iso);

    if (!$this->lock->acquire($lock_key, 15)) {
      $this->messenger()->addError($this->t('That slot is currently being booked. Please try again.'));
      return;
    }

    try {
      $booking = $this->entityTypeManager->getStorage('booking')->create([
        'name'  => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'phone' => $form_state->getValue('phone') ?? '',
        'date'  => $iso,
        'notes' => $form_state->getValue('notes') ?? '',
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

    $config   = $this->configFactory->get('booking_core.settings');
    $langcode = $this->configFactory->get('system.site')->get('langcode');
    $params   = [
      'name'  => $form_state->getValue('name'),
      'date'  => $iso,
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone') ?? '',
      'notes' => $form_state->getValue('notes') ?? '',
    ];

    $this->mailManager->mail('booking_core', 'confirmation', $form_state->getValue('email'), $langcode, $params);

    $admin_email = $config->get('admin_email') ?: $this->configFactory->get('system.site')->get('mail');
    $this->mailManager->mail('booking_core', 'notification', $admin_email, $langcode, $params);

    $this->flood->register('booking_core_submit', 3600, $this->getRequest()->getClientIp());

    $form_state->setRedirectUrl(Url::fromRoute('booking_core.thank_you'));
  }

}
