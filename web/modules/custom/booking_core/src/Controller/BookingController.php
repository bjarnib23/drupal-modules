<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\booking_core\Entity\Booking;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BookingController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function thankYou(): array {
    return [
      '#markup' => $this->t('Your booking has been received. We will be in touch shortly.'),
    ];
  }

  public function adminList(): array {
    $ids = $this->entityTypeManager->getStorage('booking')->getQuery()
      ->sort('date', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $bookings = $this->entityTypeManager->getStorage('booking')->loadMultiple($ids);
    $rows     = [];

    foreach ($bookings as $booking) {
      $date      = $booking->get('date')->value ?? '';
      $formatted = $date ? date('D d M Y \a\t H:i', strtotime($date)) : '—';

      $rows[] = [
        $booking->get('name')->value,
        $booking->get('email')->value,
        $formatted,
        Markup::create(
          Link::fromTextAndUrl('View', Url::fromRoute('booking_core.admin_view', ['booking' => $booking->id()]))->toString() .
          ' | ' .
          Link::fromTextAndUrl('Delete', Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()]))->toString()
        ),
      ];
    }

    return [
      '#type'   => 'table',
      '#header' => ['Name', 'Email', 'Date', 'Actions'],
      '#rows'   => $rows,
      '#empty'  => $this->t('No bookings yet.'),
    ];
  }

  public function adminView(Booking $booking): array {
    $date      = $booking->get('date')->value ?? '';
    $formatted = $date ? date('D d M Y \a\t H:i', strtotime($date)) : '—';

    $rows = [
      [$this->t('Name'),  $booking->get('name')->value],
      [$this->t('Email'), $booking->get('email')->value],
      [$this->t('Phone'), $booking->get('phone')->value],
      [$this->t('Date'),  $formatted],
      [$this->t('Notes'), $booking->get('notes')->value],
    ];

    return [
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Field'), $this->t('Value')],
        '#rows'   => $rows,
      ],
      'back' => [
        '#markup' => Link::fromTextAndUrl('← Back to all bookings', Url::fromRoute('booking_core.admin_list'))->toString(),
      ],
    ];
  }

}
