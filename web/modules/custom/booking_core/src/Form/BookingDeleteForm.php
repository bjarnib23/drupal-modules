<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\booking_core\Entity\Booking;

class BookingDeleteForm extends ConfirmFormBase {

  protected Booking $booking;

  public function getFormId(): string {
    return 'booking_core_delete_form';
  }

  public function getQuestion() {
    return $this->t('Are you sure you want to delete the booking for %name?', [
      '%name' => $this->booking->get('name')->value,
    ]);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('booking_core.admin_list');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?Booking $booking = NULL): array {
    $this->booking = $booking;
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->booking->delete();
    $this->messenger()->addStatus($this->t('Booking deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
