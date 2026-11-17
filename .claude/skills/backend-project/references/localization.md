# Localization Standards

## Supported Languages
- Arabic: `ar`
- English: `en`

## Default Language
- The default application locale must be `en`.

## Laravel Localization
- Prepare Laravel's localization structure by default.
- Include translation files for both Arabic and English.
- Keep translation files organized according to Laravel conventions.

## Language Middleware
- Prepare middleware that reads the `Accept-Language` request header.
- If the requested language is supported (`ar` or `en`), set it as the current application locale.
- If the requested language is missing or unsupported, use `en` as the fallback.
- Apply the locale using `App::setLocale()`.

## Usage
- Localization infrastructure must be ready in the base project.
- Individual business modules may use translations according to their requirements.
- Do not assume that every business feature requires translated content.
- Do not introduce additional languages unless explicitly requested.
