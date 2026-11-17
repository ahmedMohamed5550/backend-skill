# Authentication Standards

## Default API Authentication
- Laravel Passport is the approved default API authentication system.
- Passport must be included in the base project dependencies.
- Prepare the project to use Passport for API authentication.
- Do not replace Passport with Sanctum or another authentication system.

## Authentication Features
Authentication features must be implemented according to the project's business requirements.

Do not assume that every optional authentication feature is required.

## Google Login
- Google login is an optional feature.
- Implement it only when explicitly required by the business requirements.
- Do not assume a Google authentication package or integration mechanism that has not been approved.

## Phone OTP Verification
- Phone OTP verification is optional.
- When required, integrate it with the User Model and users table.
- Twilio is the approved default SMS provider.
- Use the existing Twilio SDK dependency.
- Store OTP values securely; never store plain-text OTP values.
- Keep provider credentials and secrets in environment variables.
- Do not hardcode credentials in application source code.

## Unconfirmed Authentication Decisions
The following decisions have not yet been finalized:
- Whether Email/Password login is enabled by default.
- The exact Google login implementation and provider package.
- The complete OTP lifecycle, expiration, retry, and rate-limiting rules.

Do not invent these decisions. Ask for clarification when they are required for implementation.

## Passport Migration Safety

- Before running Laravel Passport migrations, check whether the Passport tables already exist.
- If the required Passport tables already exist, skip creating them again.
- Do not attempt to recreate existing Passport tables.
- If only some Passport tables exist, handle the missing tables without recreating existing ones.
- Do not suppress unrelated migration errors or database errors.
- Ensure Passport migrations remain compatible with repeated migration runs.