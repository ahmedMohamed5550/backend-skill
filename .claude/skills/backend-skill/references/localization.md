# Localization Standards

## Default Language
- English (`en`) is the only default language of every generated project.
- The default application locale must be `en`, and `en` is also the fallback locale.

## Default Project: No Translation Files
- The default project contains no `lang` directory and no translation files. Laravel's skeleton ships no `lang/` directory, and the scaffold creates none.
- The default project must not depend on centralized `__('messages.*')` translation keys. Default API and authentication messages are handled directly in their relevant implementation as English literals:
  - The central exception handler (`bootstrap/app.php`) renders its envelope messages as English literals (`The provided data is invalid.`, `You are not authorized to perform this action.`, `Too many attempts. Please try again later.`, `The requested resource was not found.`, `An unexpected error occurred.`, and the verification-link message).
  - The Auth module (`AuthService`, `AuthController`) returns its lifecycle messages as English literals (registration, login, logout, password reset, email verification).
  - Controllers and Services use the Dynamic Response Messages convention (`{Module} created successfully.` — see `references/api-standards.md`), which is already plain English.
- Do not create `lang/en/messages.php`, and do not add any translation file, unless the project requirements explicitly request additional languages.

## Detecting Requested Languages
- The user's stated project requirements are the only source for the supported languages.
- Read the requirements, list every language they explicitly name, and generate exactly those languages together with English (`en`) — the mandatory default.
- A language the requirements do not explicitly name is never generated: no `lang` directory, no translation files, no configuration, and no validation for it.
- A project whose requirements name no additional language ships exactly what the scaffold produces: English literals in the implementation and no `lang` directory at all.

## Language Middleware (Always Present)
- The approved `SetLocale` middleware ships in every project, including default ones, and stays harmless when there is nothing to translate:
  - It reads the `Accept-Language` request header.
  - It resolves the requested language against the middleware's supported-language list (`['en']` in the default project).
  - If the requested language is supported, it sets it as the current application locale with `App::setLocale()`; if the requested language is missing or unsupported, it uses `en` as the fallback.
- In the default project every `Accept-Language` header therefore resolves to `en`.

## Adding Additional Languages (Only When Explicitly Requested)
- The localization architecture stays flexible: supporting additional languages requires no architectural change.
- When the project requirements explicitly request additional languages:
  1. Publish Laravel's base language files: `php artisan lang:publish` (creates `lang/en/`, including `validation.php`).
  2. Create `lang/en/messages.php` with the approved message keys, carrying the same English texts the implementation currently holds as literals.
  3. Create `lang/{locale}/messages.php` for each requested locale, translating every key in `lang/en/messages.php` (the English file stays the source of truth for the message keys).
  4. Convert the inline English literals in the implementation to `__('messages.*')` calls — the message keys replace the literals; the rendered texts stay identical for `en`.
  5. Add each requested locale to the supported-language list in the `SetLocale` middleware.
  6. Keep `en` as the default and fallback locale.
  7. Generate the requested configuration and validation message translations accordingly.
  8. Cover every supported language in the localization tests (see `references/testing.md`, Localization Testing).
- Do not generate the `lang` directory, translation files, configuration, or validation for any language that was not explicitly requested.

## Usage
- Default projects render every message in plain English with no translation infrastructure on disk.
- Business modules implemented in a multilingual project may use translations according to their requirements, backed by the generated `lang/{locale}` structure.
- Do not assume that every business feature requires translated content.
