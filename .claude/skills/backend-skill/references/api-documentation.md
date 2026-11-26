# API Documentation Generation Standard

## 1. Purpose

This document defines the mandatory standard for generating and maintaining detailed API documentation for every backend project.

The AI MUST generate a complete API documentation file based on the actual implemented API, following the project's approved API standards.

The generated documentation is intended for:

- Frontend developers.
- Mobile developers.
- Backend developers.
- AI agents that need to understand, consume, or modify the existing API.

This documentation is separate from Swagger/OpenAPI documentation.

## 2. Documentation Output

The AI MUST generate the following file inside the generated project:

`docs/API_DOCUMENTATION.md`

The file must document the complete API implementation, including every module, standard CRUD operation, and additional business action.

Do not generate documentation containing only endpoint names or basic Swagger descriptions.

The generated file MUST start with the mandatory **Public Authentication Configuration** section (see 4.1): its `client_id` and `redirect_uri` are resolved dynamically from the seeded First Party PKCE Client in the database, plus `code_challenge_method: S256` (see `references/authentication.md`, Token Lifecycle).

The file is served publicly through the standard documentation endpoint `GET /api/v1/documentation`, so frontend and mobile clients can access it without authentication. The endpoint returns the documentation beginning with that section and rebuilds its values from the database at request time, so the response always carries the current runtime configuration and the Frontend reads it directly from the documentation response. When the client is not seeded, the endpoint serves the documentation without the section.

The file itself must never contain real credentials, secrets, private keys, or access tokens; the Public Authentication Configuration resolves public values only. When the documentation is regenerated, the endpoint serves the updated file automatically. This endpoint is itself part of the API and must be documented as a public endpoint, including its leading configuration section.

## 3. Source of Truth

Documentation MUST be generated from the actual project implementation.

Before generating or updating the documentation, inspect the relevant:

- API route files.
- Controllers and controller methods.
- Form Requests and validation rules.
- API Resources.
- Services and business logic.
- Authentication and authorization middleware.
- Permission definitions and seeders.
- Models and relationships.
- API response handling.
- Relevant database migrations when necessary.

Do not assume that an endpoint, parameter, permission, response field, or business rule exists without verifying it in the implementation.

Never invent missing implementation details.

If an implementation detail cannot be verified, explicitly mark it as `Not verified` rather than guessing.

## 4. Required Documentation Structure

The generated `docs/API_DOCUMENTATION.md` MUST contain the following sections, in this order:

### 4.1 Public Authentication Configuration (mandatory first section)

`docs/API_DOCUMENTATION.md` MUST start with the Public Authentication Configuration section. The first line of the file MUST be exactly:

```
## Public Authentication Configuration
```

The section documents the public login configuration of the seeded First Party PKCE Client (`references/authentication.md`, Token Lifecycle):

- `client_id`: the id of the seeded First Party PKCE Client.
- `redirect_uri`: the client's first registered redirect URI.
- `code_challenge_method`: `S256`.

The `client_id` and `redirect_uri` MUST be resolved dynamically from the database when the documentation is generated: query the `oauth_clients` row named `First Party PKCE Client` (`revoked = false`) and write its actual values. Never hardcode, invent, or placeholder these values.

The section exposes only these public values. Never include a client secret, private key, or any other internal credential. The section ends with a `---` separator, followed by the rest of the documentation starting with the Project API Overview (4.2).

Required section shape:

```markdown
## Public Authentication Configuration

OAuth 2.0 authorization code + PKCE login configuration for the
first-party client (POST /api/v1/auth/login, then POST /oauth/token).
Web and Mobile use the same client and redirect URI. The client is
public: it holds no secret, and PKCE protects the token exchange.

- `client_id`: <value resolved from the database>
- `redirect_uri`: <value resolved from the database>
- `code_challenge_method`: `S256`

---
```

The public documentation endpoint (`GET /api/v1/documentation`) serves the file beginning with this section and rebuilds its values from the database at request time, so the Frontend always receives the current runtime configuration directly from the documentation response. When the client is not seeded, the endpoint serves the documentation without the section.

### 4.2 Project API Overview

Document:

- Project name.
- API version.
- Base URL or configured API prefix.
- Authentication mechanism.
- Default response structure.
- Supported languages when applicable.
- General pagination and sorting conventions.

Do not invent production URLs. Use the configured URL or a clearly identified placeholder when the deployment URL is not available.

### 4.3 Authentication

Document:

- Authentication mechanism.
- Token type.
- How clients obtain authentication tokens (the login flow uses the public client configuration documented in the Public Authentication Configuration section, 4.1 — do not repeat the client values here).
- How clients send authentication tokens.
- Token-related requirements.
- The registration lifecycle: `POST /api/v1/auth/register` (public, creates the account and automatically sends the native verification email) — see references/authentication.md, Registration.
- The password reset lifecycle: `POST /api/v1/auth/forgot-password` (neutral success answer, frontend reset link via `FRONTEND_URL`) and `POST /api/v1/auth/reset-password` (single-use, 60-minute token) — see references/authentication.md, Password Reset.
- The email verification lifecycle: the signed `GET /api/v1/auth/email/verify/{uuid}/{hash}` link (60-minute signature, addressing the user by uuid) and the authenticated `POST /api/v1/auth/email/resend` — see references/authentication.md, Email Verification.
- Public endpoints that do not require authentication (register, login, forgot-password, reset-password, and the signed verification link).

Never include real credentials, secrets, private keys, or access tokens.

### 4.4 Permissions Overview

Document:

- Permission naming convention.
- Permission associated with each protected endpoint.
- Public endpoints that do not require permissions.
- Any additional authorization requirements.

Permission names MUST match the actual permission definitions and middleware used by the project.

### 4.5 Modules Index

Create an index of all implemented API modules.

For each module, include:

- Module name.
- Module purpose.
- Available endpoints.
- Related resources.
- Relevant relationships with other modules.

### 4.6 Module Documentation

Create a separate detailed section for every module.

Each module MUST document all its implemented actions, including standard CRUD operations and additional business actions.

For every action, document the following:

#### A. Action Information

- Action name.
- HTTP method.
- Complete endpoint path.
- Route name when available.
- Authentication requirement.
- Required permission.
- Short description of the action.

#### B. Request Headers

Document required headers, including:

- Authorization.
- Accept.
- Content-Type.
- Accept-Language when applicable.

Only document headers that are actually required or supported by the implementation.

#### C. Path Parameters

For every path parameter, document:

- Parameter name.
- Data type.
- Required status.
- Description.
- Validation or lookup behavior.

#### D. Query Parameters

For every query parameter, document:

- Parameter name.
- Data type.
- Required or optional status.
- Default value.
- Allowed values.
- Description.
- Validation rules when applicable.

For List endpoints, document the approved pagination and sorting parameters:

- `sortBy`
- `per_page`
- `sortDirection`

Document additional filters only when they exist in the implementation.

#### E. Request Body

For actions that accept a request body, document:

- Field name.
- Data type.
- Required or optional status.
- Validation rules.
- Allowed values.
- Default values when applicable.
- Description.
- Relationships or referenced UUIDs.

Provide a complete JSON request example based on the actual Form Request and implementation.

Do not document fields that are not accepted by the endpoint.

#### F. Validation Rules

Document the actual validation rules from the corresponding Form Request.

Include:

- Required fields.
- Optional fields.
- Data types.
- Maximum and minimum values.
- Unique constraints.
- UUID existence checks.
- Enum allowed values.
- Conditional validation rules.

Do not replace actual validation rules with assumptions.

#### G. Success Response

Document:

- HTTP status code.
- Dynamic response message.
- Response structure.
- Returned data fields.
- Field data types.
- For List endpoints, the paginator structure nested inside `data` (`data`, `links`, `meta`).

Provide a realistic JSON example matching the actual API Resource and `ApiResponseTrait`.

The response MUST follow the approved API response structure:

- `message`
- `status_code`
- `data` — for List endpoints it nests Laravel's paginator structure (`data`, `links`, `meta`).

Do not invent response fields or return structures.

#### H. Error Responses

Document relevant error scenarios and their actual HTTP status codes.

Examples may include:

- Validation errors.
- Unauthenticated requests.
- Unauthorized requests.
- Resource not found.
- Business rule violations.
- Unexpected server errors.

Provide response examples using the approved API response structure.

Never expose internal exception messages, SQL errors, stack traces, credentials, or implementation details.

#### I. Business Workflow

Explain how the action works from a business perspective.

Where applicable, document:

- Business rules.
- Preconditions.
- Database operations.
- Related entities.
- Relationships affected.
- Transaction behavior.
- Side effects.
- Events or notifications.
- Conditions that cause the operation to fail.

Base this explanation on the actual Service implementation and relevant business logic.

Do not expose internal code or unnecessary implementation details.

#### J. Related Actions

Mention related endpoints when they are relevant to understanding the action's workflow.

Do not invent relationships between endpoints.

## 5. Standard CRUD Documentation

Every implemented standard CRUD operation MUST be documented separately:

- List.
- Show.
- Create.
- Update.
- Delete.
- Bulk Delete.

If an operation is intentionally not implemented, do not document it as an available endpoint.

For List operations, document pagination, sorting, filtering, and the actual List Resource structure.

For Show operations, document the UUID parameter and the actual Show Resource structure.

For Delete operations, document UUID handling and relevant deletion behavior.

For Bulk Delete operations, document the UUID collection, all-or-nothing behavior, existence verification, and transaction behavior when implemented.

## 6. Additional Business Actions

CRUD operations are not the only allowed module actions.

The AI MUST document every additional implemented business action.

Examples include:

- Export Report.
- Activate.
- Deactivate.
- Approve.
- Reject.
- Assign.
- Cancel.
- Complete.

For each additional action, document its endpoint, HTTP method, permission, request, validation, response, and business workflow.

Do not restrict documentation to CRUD operations.

## 7. Collections and Resources

Document every API Resource used by the project.

For each Resource, explain:

- Resource name.
- Module.
- Purpose.
- Returned fields.
- Field data types.
- Nested relationships.
- Conditional fields.
- Example JSON representation.

Clearly distinguish between List Resources and Show Resources.

Do not assume that both Resources return identical fields.

## 8. Relationships and Data Dependencies

Document relevant relationships between modules and entities.

For each relationship, include:

- Related module or entity.
- Relationship type.
- Referenced UUID field.
- Whether the relationship is required or optional.
- Relevant API operations.

Document only relationships verified in Models, migrations, Requests, Resources, and Services.

## 9. Pagination and Sorting

Document the actual pagination and sorting behavior.

Use the approved parameters:

- `sortBy`: defaults to `id`.
- `per_page`: defaults to `Helpers::getPagination()`.
- `sortDirection`: defaults to `desc`.

Document allowed sorting columns and accepted sorting directions based on the actual implementation.

Explain how pagination metadata is returned inside the response's `data.meta` field, alongside the nested `data.data` records and `data.links`.

Do not invent alternative parameter names or defaults.

## 10. Swagger/OpenAPI Consistency

The generated Markdown documentation and Swagger/OpenAPI documentation MUST describe the same implemented API.

They must agree on:

- HTTP methods.
- Endpoint paths.
- Authentication requirements.
- Permissions.
- Request parameters.
- Request body fields.
- Validation constraints.
- Response structures.
- HTTP status codes.

Swagger/OpenAPI and Markdown documentation serve different purposes, but neither may contradict the actual implementation.

## 11. Documentation Generation Workflow

The AI MUST follow this workflow:

1. Complete the required backend implementation.
2. Verify the implemented routes and available endpoints.
3. Inspect every module and its Controller actions.
4. Inspect corresponding Form Requests and validation rules.
5. Inspect Resources and actual response structures.
6. Inspect Services and business workflows.
7. Verify authentication and permission requirements.
8. Resolve the seeded First Party PKCE Client from the database: its `client_id` and first registered `redirect_uri` (query the `oauth_clients` row named `First Party PKCE Client` with `revoked = false`). If the clients have not been seeded yet, run the seeders before generating the documentation — the values must come from the database, never from assumptions.
9. Generate or update `docs/API_DOCUMENTATION.md`, starting with the Public Authentication Configuration section carrying the resolved values (4.1).
10. Generate or update Swagger/OpenAPI documentation.
11. Compare both documentation outputs against the actual implementation.
12. Correct missing, outdated, or inconsistent documentation.

Do not generate final API documentation based only on the initial project requirements.

Generate it after the actual implementation is available.

## 12. Documentation Maintenance

The API documentation is a required project artifact and MUST remain synchronized with the implementation.

Whenever an endpoint or its behavior changes, update the relevant documentation.

This includes:

- Adding a new module.
- Adding an endpoint.
- Modifying an existing endpoint.
- Changing request fields.
- Changing validation rules.
- Changing response fields.
- Changing permissions.
- Changing authentication requirements.
- Changing business workflows.
- Removing an endpoint.

Do not leave outdated endpoints or permissions documented as active.

## 13. Final Verification Checklist

Before completing the backend project, verify:

- [ ] Every implemented module is documented.
- [ ] Every implemented endpoint is documented.
- [ ] All CRUD operations are documented when implemented.
- [ ] All additional business actions are documented.
- [ ] Every protected endpoint has the correct permission.
- [ ] Public endpoints are clearly identified.
- [ ] Authentication requirements are accurate.
- [ ] All request parameters are documented.
- [ ] All request body fields match the actual Form Requests.
- [ ] Validation rules match the implementation.
- [ ] List and Show Resources are documented separately.
- [ ] Success response examples match actual API responses.
- [ ] Error response examples follow the approved response structure.
- [ ] Business workflows match the actual Service implementation.
- [ ] Pagination and sorting conventions are accurate.
- [ ] Relationships and UUID references are accurate.
- [ ] Markdown documentation matches Swagger/OpenAPI.
- [ ] No invented or outdated endpoints remain.
- [ ] `docs/API_DOCUMENTATION.md` starts with the Public Authentication Configuration section (first line exactly `## Public Authentication Configuration`), and its `client_id` and `redirect_uri` are the seeded First Party PKCE Client's actual database values — never hardcoded or invented — with `code_challenge_method: S256` and no client secret.
- [ ] The public documentation endpoint (`GET /api/v1/documentation`) serves the generated file.
- [ ] The public documentation endpoint response begins with the Public Authentication Configuration section carrying the runtime database values, so the Frontend reads the login configuration directly from the documentation response.

The backend project MUST NOT be considered fully documented until this checklist has been verified.