<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

class BookingDeleteForm extends ContentEntityDeleteForm {

  protected function getRedirectUrl(): Url {
    return Url::fromRoute('booking_core.admin_list');
  }

}
