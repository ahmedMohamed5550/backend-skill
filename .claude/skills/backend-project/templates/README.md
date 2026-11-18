# {{PROJECT_NAME}}

{{PROJECT_DESCRIPTION}}

## Requirements

- PHP ^8.4
- Composer
- MySQL
- Laravel ^13.0

## Setup

1. Install dependencies:

    ```bash
    composer install
    ```

2. Configure the environment:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    Default local database connection: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USERNAME=root`, empty `DB_PASSWORD`. The configured database name is `{{DB_NAME}}`.

3. Run the migrations:

    ```bash
    php artisan migrate
    ```

4. Prepare Laravel Passport:

    ```bash
    php artisan passport:keys
    php artisan passport:client --personal --name="Personal Access Client"
    ```

5. Run the seeders after the module seeders are registered:

    ```bash
    php artisan db:seed
    ```

6. Start the local development server:

    ```bash
    php artisan serve
    ```

## API Documentation

- Swagger UI: `{APP_URL}/api/documentation` (L5-Swagger default route)
- Public documentation endpoint: `GET {APP_URL}/api/v1/documentation` (serves `docs/API_DOCUMENTATION.md`, no authentication required)
- Markdown documentation file: `docs/API_DOCUMENTATION.md`

## Authentication

Laravel Passport is the default API authentication system. Roles and permissions are managed with Spatie Laravel Permission.

## Localization

The API supports `en` (default) and `ar` through the `Accept-Language` header.
