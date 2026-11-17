# Backend Dependencies Standards

## Technology Stack
- PHP: `^8.2`
- Laravel Framework: `^13.0`
- API Authentication: Laravel Passport

Use the approved versions as supplied. Do not silently change version constraints.

Before generating or installing dependencies, verify that the PHP and Laravel version constraints are mutually compatible. If they are incompatible, stop and report the conflict instead of changing either version without approval.

## Base Project Dependencies

All packages listed below must initially be included in the base Composer configuration.

Business requirements may determine which packages are actually used or removed later. Do not omit packages during initial scaffold creation.

### Production Dependencies

- `agence104/livekit-server-sdk`: `^1.3`
- `barryvdh/laravel-dompdf`: `^3.1`
- `darkaonline/l5-swagger`: `^8.6`
- `doctrine/dbal`: `^3.8`
- `docusign/esign-client`: `^8.8`
- `firebase/php-jwt`: `^7.0`
- `google/apiclient`: `^2.18`
- `guzzlehttp/guzzle`: `^7.0.1`
- `kreait/laravel-firebase`: `^7.2`
- `laravel/cashier`: `^15.6`
- `laravel/fortify`: `^1.25`
- `laravel/nightwatch`: `^1.30`
- `laravel/passport`: `^13`
- `laravel/pulse`: `^1.7`
- `laravel/reverb`: `^1.12`
- `laravel/sanctum`: `^4.2`
- `laravel/telescope`: `^5.2`
- `laravel/tinker`: `^3.0`
- `laravel/ui`: `^4.5`
- `livewire/livewire`: `^3.6`
- `maatwebsite/excel`: `^3.1`
- `nnjeim/world`: `^1.1`
- `opcodesio/log-viewer`: `*`
- `openspout/openspout`: `^5.13`
- `phpmailer/phpmailer`: `^6.8`
- `pinoox/numera`: `*`
- `pragmarx/google2fa-laravel`: `*`
- `predis/predis`: `^2.2`
- `promphp/prometheus_client_php`: `^2.11.0`
- `pusher/pusher-php-server`: `^7.2`
- `ringcentral/ringcentral-php`: `^3.0.1`
- `sendgrid/sendgrid`: `^8.1`
- `sentry/sentry-laravel`: `^4.13`
- `setasign/fpdf`: `^1.9`
- `setasign/fpdi`: `^2.6`
- `spatie/laravel-activitylog`: `^4.8`
- `spatie/laravel-passkeys`: `^1.8`
- `spatie/laravel-permission`: `^6.0`
- `spatie/laravel-prometheus`: `^1.4`
- `spomky-labs/otphp`: `*`
- `stripe/stripe-php`: `*`
- `twilio/sdk`: `^7.13`

### Development Dependencies

- `doctrine/annotations`: `^2.0`
- `fakerphp/faker`: `^1.9.1`
- `laravel/boost`: `^2.4`
- `laravel/pint`: `^1.19`
- `laravel/sail`: `^1.0.1`
- `mockery/mockery`: `^1.6`
- `nunomaduro/collision`: `^8.5`
- `phpunit/phpunit`: `^12.0`
- `spatie/laravel-ignition`: `^2.9`
- `squizlabs/php_codesniffer`: `^3.11`

## Composer PSR-4 Autoload

Preserve these mappings:

- `App\\`: `app/`
- `Database\\Factories\\`: `database/factories/`
- `Database\\Seeders\\`: `database/seeders/`

## Composer File Autoload

Preserve these entries:

- `app/Helpers/Helpers.php`
- `app/Helpers/UtilityHelper.php`
- `app/Helpers/FileHelper.php`

## Composer Scripts
- Preserve the standard Laravel project setup and package-discovery scripts.
- Preserve the required application key generation and normal Laravel Composer lifecycle scripts.
- Do not remove or replace standard Laravel scripts without approval.

## Dependency Rules
- Do not add unapproved packages.
- Do not replace approved packages with alternatives.
- Do not silently change package versions.
- Do not remove base dependencies during initial scaffold creation.
- Package removal may happen later when business requirements are reviewed and approved.
- Verify dependency compatibility before running Composer installation.
