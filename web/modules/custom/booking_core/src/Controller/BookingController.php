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
        $booking->get('service')->value ?: '—',
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
      '#header' => ['Name', 'Email', 'Service', 'Date', 'Actions'],
      '#rows'   => $rows,
      '#empty'  => $this->t('No bookings yet.'),
    ];
  }

  public function adminView(Booking $booking): array {
    $date      = $booking->get('date')->value ?? '';
    $formatted = $date ? date('D d M Y \a\t H:i', strtotime($date)) : '—';

    $fields = [
      'Name'    => $booking->get('name')->value,
      'Email'   => $booking->get('email')->value,
      'Phone'   => $booking->get('phone')->value ?: '—',
      'Service' => $booking->get('service')->value ?: '—',
      'Date'    => $formatted,
      'Notes'   => $booking->get('notes')->value ?: '—',
    ];

    $rows = '';
    foreach ($fields as $label => $value) {
      $rows .= '<div style="display:flex;padding:12px 0;border-bottom:1px solid #e5e7eb;">'
        . '<div style="width:160px;font-weight:600;color:#374151;">' . htmlspecialchars($label) . '</div>'
        . '<div style="flex:1;color:#111827;">' . htmlspecialchars($value) . '</div>'
        . '</div>';
    }

    $delete_url  = Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()])->toString();
    $list_url    = Url::fromRoute('booking_core.admin_list')->toString();

    return [
      '#markup' => Markup::create(
        '<div style="max-width:680px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:28px 32px;margin-top:16px;">'
        . '<div style="margin-bottom:20px;">' . $rows . '</div>'
        . '<div style="display:flex;gap:12px;margin-top:24px;">'
        . '<a href="' . $list_url . '" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#374151;font-size:14px;">← All bookings</a>'
        . '<a href="' . $delete_url . '" style="padding:8px 16px;background:#dc2626;border-radius:6px;text-decoration:none;color:#fff;font-size:14px;">Delete booking</a>'
        . '</div>'
        . '</div>'
      ),
    ];
  }

}
