<?php

namespace Drupal\giftcard_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Gift Card Core module.
 */
class GiftCardSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'giftcard_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['giftcard_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('giftcard_core.settings');

    $form['currency'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Currency code'),
      '#description'   => $this->t('ISO 4217 currency code for gift card amounts (e.g. ISK, EUR, USD).'),
      '#default_value' => $config->get('currency'),
      '#size'          => 5,
      '#maxlength'     => 3,
      '#required'      => TRUE,
    ];

    $form['min_amount'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Minimum gift card amount'),
      '#description'   => $this->t('Smallest purchase amount allowed, in whole units of the configured currency (e.g. 1000 = 1000 ISK).'),
      '#default_value' => $config->get('min_amount'),
      '#min'           => 1,
      '#required'      => TRUE,
    ];

    $form['flood_threshold'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Max checkout attempts per hour per IP'),
      '#description'   => $this->t('Requests beyond this limit are blocked to prevent abuse.'),
      '#default_value' => $config->get('flood_threshold'),
      '#min'           => 1,
      '#max'           => 100,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('giftcard_core.settings')
      ->set('currency', strtoupper(trim($form_state->getValue('currency'))))
      ->set('min_amount', (int) $form_state->getValue('min_amount'))
      ->set('flood_threshold', (int) $form_state->getValue('flood_threshold'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
