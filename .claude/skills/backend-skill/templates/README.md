# {{PROJECT_NAME}}

{{PROJECT_DESCRIPTION}}

## Project Status

This project is `BLOCKED` by default. It becomes `READY` only after the Backend Skill Completion Gate passes, which requires:

- All Backend Skill requirements fully implemented.
- Composer and all dependencies installed successfully.
- Database setup, migrations, seeders, and required configurations completed.
- All routes, authentication, and integrations working correctly.
- Swagger and all required documentation generated and verified.
- The complete required test suite passing.
- The application booting and running successfully.

Run the gate from the skill workspace: `bash scripts/completion-gate.sh <project-folder-name>`. A `BLOCKED` project must not be handed off to the Frontend Team or any other team. Update this section to `READY` only after the gate passes.

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

    Wait for this step to finish before running any `php artisan` command. While Composer is still installing, the project has no `vendor/autoload.php` yet and `php artisan serve` fails with "Failed opening required .../vendor/autoload.php".

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
    ```

5. Run the seeders after the module seeders are registered. Seeding creates the Passport personal access client (`database/seeders/PassportClientSeeder.php`), which is required to issue login tokens:

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
