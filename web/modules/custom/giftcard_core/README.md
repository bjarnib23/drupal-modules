# Gift Card Core

A standalone Drupal module for selling digital gift cards through a configurable
payment provider. Buyers fill in a purchase form, pay via a hosted checkout page,
and both the sender and recipient receive confirmation emails once the payment is
confirmed by a webhook.

## Features

- Custom **GiftCard** content entity with admin list, add, edit, and delete views.
- **PaymentClientInterface** — swap the payment provider without touching the module.
- Built-in **RapydClient** implementation using HMAC-signed requests.
- Flood control to limit checkout attempts per IP per hour.
- Configurable currency, country, minimum amount, and flood threshold.
- Confirmation emails to both sender and recipient on successful payment.

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- [Key](https://www.drupal.org/project/key) module — stores API credentials securely

## Installation

1. Place the module folder inside `web/modules/custom/giftcard_core`.
2. Enable the module:

   ```
   drush en giftcard_core -y
   ```

3. Create two **Key** entities at **Administration → Configuration → System → Keys**:
   - One for the Rapyd *access key*.
   - One for the Rapyd *secret key*.

4. Open **Administration → Configuration → Gift Card → Settings** and fill in:
   - The machine names of the two Key entities.
   - Whether to use the Rapyd sandbox environment.
   - The ISO 3166-1 alpha-2 country code (e.g. `IS`).
   - The ISO 4217 currency code (e.g. `ISK`).
   - The minimum gift card amount.
   - The maximum checkout attempts per hour per IP.

5. Configure Rapyd to send `PAYMENT_COMPLETED` webhooks to:

   ```
   https://your-site.example/gift-card/webhook
   ```

## Configuration

All settings are stored under the `giftcard_core.settings` configuration object.

| Key | Type | Description |
|-----|------|-------------|
| `rapyd_access_key_id` | string | Machine name of the Key entity holding the Rapyd access key |
| `rapyd_secret_key_id` | string | Machine name of the Key entity holding the Rapyd secret key |
| `rapyd_sandbox` | boolean | Use the Rapyd sandbox API when `true` |
| `rapyd_country` | string | ISO 3166-1 alpha-2 country code sent to Rapyd |
| `currency` | string | ISO 4217 currency code for gift card amounts |
| `min_amount` | integer | Smallest purchase amount allowed |
| `flood_threshold` | integer | Max checkout attempts per IP per hour (default: 5) |

## Permissions

| Permission | Description |
|------------|-------------|
| `administer gift cards` | Full CRUD access to gift card entities and module settings |
| `view gift card list` | View the admin gift card list without editing |

## Swapping the Payment Provider

The module registers `giftcard_core.payment_client` as a service that points to
`RapydClient`. To use a different payment provider:

1. Create a class that implements `\Drupal\giftcard_core\PaymentClientInterface`.
2. Override the service in your own module's `*.services.yml`:

   ```yaml
   giftcard_core.payment_client:
     class: Drupal\my_module\MyPaymentClient
     arguments: ['@config.factory']
   ```

The two methods you must implement are:

- `createCheckout(int $amount, string $currency, string $country, string $completeUrl, string $cancelUrl): ?array`
  — returns `['payment_id' => '...', 'redirect_url' => '...']` or `null` on failure.
- `verifyWebhookSignature(string $body, string $signature): bool`
  — returns `true` when the incoming webhook request is authentic.

## Routes

| Path | Description |
|------|-------------|
| `/gift-card/buy` | Public purchase form |
| `/gift-card/buy/thank-you` | Confirmation page shown after redirect back from payment provider |
| `/gift-card/buy/cancel` | Cancellation page |
| `/gift-card/webhook` | Webhook endpoint (POST, no CSRF token required) |
| `/admin/config/giftcard-core/settings` | Module settings form |

## Maintainers

- [Vigfús Hauksson](https://www.drupal.org/u/vigfushauksson)
