<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\booking_core\BookingInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for deleting a booking.
 */
class BookingDeleteForm extends ConfirmFormBase {

  protected BookingInterface $booking;

  public function getFormId(): string {
    return 'booking_core_delete_form';
  }

  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the booking for %name?', [
      '%name' => $this->booking->getName(),
    ]);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('booking_core.admin_list');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?BookingInterface $booking = NULL): array {
    if ($booking === NULL) {
      throw new NotFoundHttpException();
    }
    $this->booking = $booking;
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->booking->delete();
    $this->messenger()->addStatus($this->t('Booking deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
