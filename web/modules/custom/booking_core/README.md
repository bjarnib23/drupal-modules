# Booking Core

A standalone Drupal module that provides appointment booking functionality using a custom entity.

## Features

- Custom `Booking` content entity stored in its own database table
- Public booking form at `/book-appointment` with flood control and lock-based double-booking prevention
- Email confirmation to the customer and notification to the admin on every booking
- Admin list, view, and delete UI at `/admin/bookings`
- Configurable company name and admin email at `/admin/config/booking-core/settings`

## Installation

```bash
drush pm:install booking_core
```

After installation, configure the module at **Admin → Configuration → Booking Settings**.

## Running tests

```bash
vendor/bin/phpunit -c web/core/phpunit.xml web/modules/custom/booking_core/tests/ --testdox
```

## Requirements

- Drupal 10 or 11
- `datetime` core module
