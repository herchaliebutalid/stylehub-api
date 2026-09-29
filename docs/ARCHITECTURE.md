# StyleHub Backend Architecture

## Project

StyleHub: A SaaS-Based Barber Shop and Beauty Parlor Management System with Mobile Booking and SMS Notifications.

## Backend Stack

- Laravel 13
- PHP 8.4
- MySQL
- InnoDB
- Laravel REST API
- Laravel Sanctum for mobile API authentication
- React Native client
- Semaphore SMS integration prepared for future activation

## Primary User Roles

The application uses a single users table.

Supported roles:

- customer
- shop_owner
- admin

Authorization must always be enforced by Laravel.

The React Native application must never be trusted to determine whether a user is permitted to perform an action.

## User Status

Supported account statuses:

- active
- suspended

User status is separate from the user's role.

## Shop Approval

Supported approval states:

- pending
- approved
- rejected

Only approved shops may be displayed to customers.

Shop approval is separate from the shop owner's account status.

## Shop Availability

Supported availability states:

- open
- closed
- fully_booked
- accepting_walk_ins

## Core Tables

- users
- shops
- services
- shop_schedules
- appointments
- reschedule_requests
- promos
- feedback
- sms_notifications
- audit_logs
- personal_access_tokens

Laravel infrastructure tables are also retained.

## Relationships

A user with the shop_owner role may own a shop.

A shop belongs to one shop owner.

A shop has many services.

A shop has many schedules.

A shop has many appointments.

A shop has many promos.

A shop has many feedback records.

A customer may have many appointments.

An appointment belongs to one customer.

An appointment belongs to one shop.

An appointment belongs to one service.

An appointment may have multiple historical reschedule requests.

An appointment may have at most one feedback record.

## Appointment Statuses

Supported appointment statuses:

- pending
- confirmed
- completed
- cancelled

## Reschedule Statuses

Supported reschedule statuses:

- pending
- approved
- rejected
- cancelled

## SMS Statuses

Supported SMS statuses:

- pending
- sent
- failed
- skipped

SMS delivery must never determine whether an appointment is successfully stored.

Appointments must be committed independently of external SMS provider availability.

## Tenant Isolation

Shop owners must only access data belonging to their own shop.

This rule applies to:

- services
- schedules
- appointments
- reschedule requests
- promos
- feedback
- shop settings

Authorization must be enforced by Laravel policies and backend queries.

Client-side filtering is not considered a security control.

## Database Integrity

StyleHub uses InnoDB.

Foreign keys must be used for relationships where appropriate.

Database transactions must be used for operations that modify multiple related records.

Indexes must be added to frequently searched columns and foreign keys.

Double-booking protection must be enforced on the backend and must account for concurrent requests.

## Authentication

Laravel Sanctum will be used for mobile API token authentication.

Passwords must never be stored in plaintext.

API tokens and server credentials must never be stored directly in source code.

## Secrets

Secrets belong in the Laravel environment configuration.

Examples:

- APP_KEY
- database passwords
- Semaphore API keys
- production service credentials

The .env file must never be committed to Git.

Secrets must never be embedded inside the React Native application.

## Input Security

All external data must be treated as untrusted.

Laravel Form Request validation will be used for API input validation.

Mass assignment must be controlled.

User-provided identifiers must not be trusted for authorization.

Database queries must use Eloquent or parameterized query builder operations.

Raw SQL containing concatenated user input is prohibited.

## API Security

Protected routes require authentication.

Role and ownership authorization must be checked server-side.

Sensitive endpoints must use rate limiting where appropriate.

Authentication endpoints must use stricter rate limiting.

Production traffic must use HTTPS.

Debug mode must be disabled in production.

Internal stack traces must not be returned to production clients.

## Auditability

Security-sensitive and business-critical actions should be recorded.

Examples include:

- administrator shop approval
- administrator shop rejection
- account suspension
- service price changes
- appointment cancellation
- reschedule approval or rejection
- shop availability changes

## File Upload Security

If image uploads are introduced:

- validate MIME type
- validate extension
- enforce file-size limits
- generate server-side filenames
- never trust original filenames
- prevent executable uploads
- store files outside executable application paths where appropriate

## SMS Architecture

Semaphore integration must be isolated behind a service layer.

Application code must not directly scatter Semaphore HTTP requests across controllers.

Initial development configuration:

SEMAPHORE_ENABLED=false

When disabled, StyleHub must continue to function normally without sending live SMS messages.

When Semaphore is activated later, credentials must be supplied through environment configuration.

## Testing Strategy

Automated tests will cover:

- authentication
- role authorization
- tenant isolation
- shop approval
- service ownership
- appointments
- double-booking protection
- rescheduling
- feedback eligibility
- administrator actions
- SMS-disabled behavior

Security tests must include attempts to access records belonging to another user or shop.

## Development Rule

After meaningful backend changes:

1. Run Laravel automated tests.
2. Review Git changes.
3. Run Composer security audit when dependencies change.
4. Commit only after the application is in a known working state.