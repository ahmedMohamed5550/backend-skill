# API Standards

## API Versioning
- All API endpoints belong to Version 1.
- Main routes file: `routes/api1.php`.
- `routes/api1.php` must be the only skill-generated route file directly inside `routes/`.
- Each module has its own route file inside `routes/v1/`, including the Auth and User modules.
- The main API routes file must require every generated `routes/v1/` route file.
- Do not create route subdirectories other than `routes/v1/`.

## Controllers and Services
- Each module must have exactly one Controller and one Service.
- Controllers receive HTTP requests and delegate operations to the module Service.
- Services handle business logic and database query operations.
- Do not put business logic or database query implementation directly in Controllers.

## Form Requests
- Create Form Requests when input validation is required.
- Create Requests especially for Create and Update operations.
- Do not create default Form Requests for List, Show, or Delete operations.
- Delete operations receive UUID parameters directly.
- Required business fields must be validated through Form Requests.

## API Resources
- Each module must have exactly two Resources:
  - List Resource
  - Show Resource
- Use the List Resource for collection/list responses.
- Use the Show Resource for individual record responses.
- Do not create additional Resources unless explicitly approved.

## API Response Structure
All API responses must use the approved `ApiResponseTrait` method `apiResponse()`.

The response structure must be:

    {
        "message": "Response message",
        "status_code": 200,
        "data": {},
        "pagination": {}
    }

- `message` is required.
- `status_code` is required.
- `data` is required.
- `pagination` is optional and must only be included when applicable.
- Do not return alternative JSON response structures.

## Response Consistency
- Success responses must use `apiResponse()`.
- Error responses must use the same response structure.
- Validation errors must use the same response structure.
- Exceptions must use the same response structure.
- Configure centralized exception and validation handling so responses follow this format even when an exception occurs before reaching a Controller.
- Do not return Laravel's default validation or exception JSON structure.

## Dynamic Response Messages

- Every success response message must dynamically include the module name and the performed action.
- Never use generic messages such as `Operation completed successfully.`
- Use the following action messages:
  - Create: `{Module} created successfully.`
  - Update: `{Module} updated successfully.`
  - List: `{Module} retrieved successfully.`
  - Show: `{Module} retrieved successfully.`
  - Delete: `{Module} deleted successfully.`
  - Bulk Delete: `{Module} deleted successfully.`
- Replace `{Module}` with the actual module name in the response.
- Examples:
  - `Categories created successfully.`
  - `Categories updated successfully.`
  - `Products retrieved successfully.`
  - `Orders deleted successfully.`
- Keep the existing API response structure unchanged.
- Apply this convention consistently across all modules and endpoints.

## Delete Operations
- Single Delete identifies the record by UUID.
- Bulk Delete accepts a collection of UUIDs.
- Bulk Delete must verify that all UUIDs exist before deleting.
- If any UUID is missing, the entire operation must fail without partial deletion.
- Bulk Delete must run inside a database transaction.

## List Operations
- Every List endpoint supports pagination and sorting.
- Filtering is implemented according to business requirements.
- Query and filter logic belongs in the Service.
- Use the approved helper to retrieve the basic request parameters.
- Do not invent alternative parameter names or defaults.

Approved parameters:
- `sortBy`: defaults to `id`.
- `per_page`: defaults to `Helpers::getPagination()`.
- `sortDirection`: defaults to `desc`.

Apply sorting using `orderBy($sortBy, $sortDirection)` and pagination using `paginate($perPage)`.

- Validate the requested sort column against an allowed-column whitelist.
- Validate sort direction as either `asc` or `desc`.
- Do not allow arbitrary request values to control SQL sorting.

## Database Writes
- Every operation that changes database data must run inside `DB::transaction()`.
- Read-only operations do not require transactions.

## Controller Exception Handling

- Every Controller action must be wrapped in a try/catch block.
- Catch exceptions and handle them without exposing internal error details to API users.
- Log every caught exception using Laravel's Log facade.
- Include relevant contextual information in the log, such as UUIDs, request identifiers, and relevant entity IDs when available.
- Return errors using the approved `apiResponse()` method from `ApiResponseTrait`.
- Never pass `$e->getMessage()`, stack traces, or internal exception details as the third parameter (`data`) of `apiResponse()`.
- Return a generic, appropriate error message with HTTP status code 500.
- Keep existing success response messages and response structures unchanged.
- Handle expected business cases, such as not-found records, using their appropriate HTTP status codes.
- Do not silently ignore exceptions.
- Do not expose database queries, SQL errors, file paths, credentials, or internal implementation details in API responses.


## Route Naming Convention

- Every module must have a route prefix matching its module name in lowercase kebab-case.
- Use the module name as the base route prefix.
- Standard CRUD endpoints must follow Laravel RESTful conventions:
  - List: `GET /{module}`
  - Show: `GET /{module}/{uuid}`
  - Create: `POST /{module}`
  - Update: `PUT /{module}/{uuid}`
  - Delete: `DELETE /{module}/{uuid}`
- Any additional endpoint that is not a standard CRUD operation must use a lowercase kebab-case path.
- Additional endpoint paths must begin with `/`.
- Separate multiple words with hyphens.
- Examples:
  - `/categories`
  - `/categories/{uuid}`
  - `/categories/bulk-delete`
  - `/group-therapy-meetings`
  - `/group-therapy-meetings/export-report`
- Do not use camelCase, PascalCase, underscores, or spaces in route paths.
- Keep route naming consistent across all modules and API clients.

## Module Actions

- Every module must include the standard CRUD operations by default.
- CRUD operations are the default foundation, not a limitation on the module's functionality.
- Implement all additional actions explicitly requested by the project requirements or business logic.
- Do not restrict modules to CRUD operations only.
- Additional actions must be implemented within the existing module Controller and Service.
- Create additional Form Requests only when input validation is required.
- Register additional routes using the approved route naming convention.
- Every additional protected action must have its own permission following the approved Permission Naming Convention.
- Do not create additional Controllers, Services, or Resources unless explicitly approved.
- Do not invent additional business actions that were not requested or required by the project.

## Permission Naming Convention

- Every protected Controller action must have a permission.
- Permission names must use lowercase kebab-case.
- The permission name must begin with the module name, followed by the action name.
- Use the following standard CRUD action suffixes:
  - List: `list`
  - Show: `show`
  - Create: `create`
  - Update: `update`
  - Delete: `delete`
- Any additional non-CRUD action must use a descriptive lowercase kebab-case suffix.
- Examples:
  - `categories-list`
  - `categories-show`
  - `categories-create`
  - `categories-update`
  - `categories-delete`
  - `categories-bulk-delete`
  - `group-therapy-meetings-list`
  - `group-therapy-meetings-create`
  - `group-therapy-meetings-export-report`
- Do not use underscores, camelCase, PascalCase, or spaces.
- Permission names must match the module and action they protect.
- Use the exact same permission names in the permission seeders and Controller middleware.
- Do not generate duplicate permissions for the same module action.