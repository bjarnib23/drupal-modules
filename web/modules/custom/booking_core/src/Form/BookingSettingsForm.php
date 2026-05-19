<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Booking Core module.
 */
class BookingSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'booking_core_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['booking_core.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config   = $this->config('booking_core.settings');
    $services = $config->get('services') ?? [];
    $days     = $config->get('open_days') ?? [1, 2, 3, 4, 5];

    $form['company_name_notice'] = [
      '#type'   => 'item',
      '#markup' => $this->t('The company name used in emails is taken from the <a href="/admin/config/system/site-information">site name</a>.'),
    ];

    $form['admin_email'] = [
      '#type'          => 'email',
      '#title'         => $this->t('Admin notification email'),
      '#default_value' => $config->get('admin_email') ?? '',
    ];

    $form['services'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Available services'),
      '#description'   => $this->t('One service per line.'),
      '#default_value' => implode("\n", $services),
      '#rows'          => 6,
    ];

    $form['hours'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Business hours'),
    ];

    $form['hours']['open_days'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Open days'),
      '#options'       => [
        1 => $this->t('Monday'),
        2 => $this->t('Tuesday'),
        3 => $this->t('Wednesday'),
        4 => $this->t('Thursday'),
        5 => $this->t('Friday'),
        6 => $this->t('Saturday'),
        0 => $this->t('Sunday'),
      ],
      '#default_value' => $days,
    ];

    $form['hours']['open_time'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Opening time'),
      '#description'   => $this->t('Format: HH:MM (e.g. 09:00)'),
      '#default_value' => $config->get('open_time') ?? '09:00',
      '#size'          => 8,
    ];

    $form['hours']['close_time'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Closing time'),
      '#description'   => $this->t('Format: HH:MM (e.g. 17:00)'),
      '#default_value' => $config->get('close_time') ?? '17:00',
      '#size'          => 8,
    ];

    $form['hours']['slot_duration'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Slot duration'),
      '#options'       => [
        15  => $this->t('15 minutes'),
        30  => $this->t('30 minutes'),
        45  => $this->t('45 minutes'),
        60  => $this->t('1 hour'),
        90  => $this->t('1.5 hours'),
        120 => $this->t('2 hours'),
      ],
      '#default_value' => $config->get('slot_duration') ?? 30,
    ];

    $form['hours']['weeks_ahead'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Weeks ahead'),
      '#description'   => $this->t('How many weeks ahead customers can book.'),
      '#default_value' => $config->get('weeks_ahead') ?? 4,
      '#min'           => 1,
      '#max'           => 52,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['open_time', 'close_time'] as $field) {
      $val = $form_state->getValue($field);
      if (!preg_match('/^\d{2}:\d{2}$/', $val)) {
        $form_state->setErrorByName($field, $this->t('Time must be in HH:MM format.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw      = $form_state->getValue('services');
    $services = array_values(array_filter(array_map('trim', explode("\n", $raw))));
    $days     = array_values(array_map('intval', array_filter($form_state->getValue('open_days'))));

    $this->config('booking_core.settings')
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('services', $services)
      ->set('open_days', $days)
      ->set('open_time', $form_state->getValue('open_time'))
      ->set('close_time', $form_state->getValue('close_time'))
      ->set('slot_duration', (int) $form_state->getValue('slot_duration'))
      ->set('weeks_ahead', (int) $form_state->getValue('weeks_ahead'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
