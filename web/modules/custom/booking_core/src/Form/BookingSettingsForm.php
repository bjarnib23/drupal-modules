<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    $form['company_name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Company name'),
      '#description'   => $this->t('Used in confirmation emails.'),
      '#default_value' => $config->get('company_name') ?? '',
    ];

    $form['admin_email'] = [
      '#type'          => 'email',
      '#title'         => $this->t('Admin notification email'),
      '#description'   => $this->t('Receives a copy of every new booking.'),
      '#default_value' => $config->get('admin_email') ?? '',
    ];

    $form['services'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Available services'),
      '#description'   => $this->t('One service per line. These appear as options in the booking form.'),
      '#default_value' => implode("\n", $services),
      '#rows'          => 8,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw      = $form_state->getValue('services');
    $services = array_values(array_filter(array_map('trim', explode("\n", $raw))));

    $this->config('booking_core.settings')
      ->set('company_name', $form_state->getValue('company_name'))
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('services', $services)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
