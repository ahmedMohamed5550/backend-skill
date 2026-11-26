# Helpers and Traits Standards

## Helpers
- Location: `app/Helpers/Helpers.php`
- Namespace: `App\Helpers`
- Register only `app/Helpers/Helpers.php` in Composer `autoload.files`.
- Use the exact approved implementation from the Helpers template.
- Do not invent additional helper methods or modify approved methods without permission.

## Approved Methods
- `getBasicRequestParams(Request $request)`
- `getPagination()`
- `getCurrentLang()`
- `checkPermissions($permission, $role)`

## API Response Trait
- Location: `app/Traits/ApiResponseTrait.php`
- Namespace: `App\Traits`
- Trait name: `ApiResponseTrait`
- Use the approved `apiResponse()` method and its template implementation.
- All API responses must follow `api-standards.md`.

## Excluded Files
- Do not include `UtilityHelper.php`.
- Do not include `FileHelper.php`.
- Do not create a Constants class or depend on `Constants::DASHBOARD_PAGINATION`.
