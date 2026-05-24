<?php

namespace Drupal\rapyd_checkout\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Initiates a Rapyd Hosted Checkout session and redirects the customer.
 */
class RapydCheckoutForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order   = $payment->getOrder();

    /** @var \Drupal\rapyd_checkout\Plugin\Commerce\PaymentGateway\RapydCheckout $gateway */
    $gateway = $payment->getPaymentGateway()->getPlugin();
    $client  = $gateway->getRapydClient();

    $return_url = Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ], ['absolute' => TRUE])->toString();

    $cancel_url = Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ], ['absolute' => TRUE])->toString();

    $gateway_config = $gateway->getConfiguration();

    try {
      $result = $client->createCheckout(
        (int) $order->id(),
        (int) $order->getTotalPrice()->getNumber(),
        $gateway_config['currency'],
        $gateway_config['country'],
        $order->getEmail() ?? '',
        $return_url,
        $cancel_url,
      );
    }
    catch (\RuntimeException $e) {
      throw new PaymentGatewayException('Could not initiate Rapyd checkout: ' . $e->getMessage(), 0, $e);
    }

    $order->setData('rapyd_checkout_id', $result['checkout_id']);
    $order->save();

    return $this->buildRedirectForm($form, $form_state, $result['redirect_url'], [], self::REDIRECT_GET);
  }

}
