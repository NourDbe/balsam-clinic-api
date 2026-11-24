Balsam Clinic Management API

Week 8 Backend Assignment | Laravel Multi-Branch Clinic Management System

1. Project Overview

Balsam Clinic Management API is a RESTful backend application developed using Laravel.

The project manages a network of medical clinics operating across multiple branches, including Damascus, Aleppo, and Latakia.

The system supports doctors working in multiple branches, patient appointments, medical records, audit history, secure attachments, and automated administrative tasks.

The main objective is to build a maintainable API using Laravel's model lifecycle, event-driven architecture, background jobs, caching, and task scheduling.

The project is API-only. No custom Blade views or frontend interface are included.

Key Features

- Multi-branch clinic management
- Doctors and working schedules
- Patient management
- Secure appointment booking
- Automatic unique appointment references
- Appointment conflict prevention
- Medical records with audit history
- Role-based access control
- Polymorphic audit logs
- Polymorphic attachments
- Appointment availability caching
- Doctor directory caching
- Events and Listeners
- Background Jobs and prioritized Queues
- Redis integration
- Laravel Horizon monitoring
- Automated appointment reminders
- No-show detection and patient booking restrictions
- Monthly branch reports
- Laravel Task Scheduler
- Automated Feature Tests
- GitHub Actions verification

---

2. Technology Stack

Technology| Purpose
PHP| Backend programming language
Laravel| Application framework
MySQL| Primary database
Laravel Sanctum| API authentication
Eloquent ORM| Database models and relationships
Model Observers| Automatic lifecycle reactions
Events and Listeners| Business event handling
Laravel Queues| Background processing
Redis| Cache and queue backend
Predis| Redis PHP client
Laravel Horizon| Redis queue monitoring and supervision
Laravel Scheduler| Automated recurring tasks
Artisan Commands| Command-line automation
Form Requests| Validation and authorization
API Resources| Structured API responses
PHPUnit| Automated testing
GitHub Actions| Linux-based integration verification
Postman| API testing collection

---

3. System Architecture

The application follows a layered architecture to separate responsibilities.

Controllers

Controllers are responsible for receiving HTTP requests, calling appropriate services, and returning API responses.

Business logic is delegated to dedicated service classes.

Services

Services implement the application business rules, including:

- Appointment booking
- Appointment workflow management
- Medical record operations
- Appointment availability calculation
- Doctor directory filtering and caching
- Appointment reminders
- Missed appointment processing
- Patient booking restrictions
- Monthly reporting

Models

Models define database relationships, attribute casting, and query scopes.

Observers

Observers handle reactions associated with Eloquent model lifecycle events.

Events and Listeners

Events represent business actions such as appointment booking or cancellation.

Listeners react to these events without coupling the original booking operation to notification logic.

Jobs

Jobs handle background tasks such as simulated SMS confirmations and appointment reminders.

Artisan Commands

Commands provide command-line access to reusable service methods.

Policies and Form Requests

Policies and Form Requests are used for authorization and validation.

API Resources

API Resources format responses and provide consistent resource representations.

---

4. Database Models

The project contains the following principal models.

Model| Responsibility
User| Authentication and user roles
Branch| Clinic branch information
Doctor| Doctor profiles and specialties
DoctorSchedule| Doctor working hours by branch and weekday
Patient| Patient information and booking restrictions
Appointment| Medical appointments
MedicalRecord| Diagnosis, prescription, and notes
AuditLog| Polymorphic change history
Attachment| Polymorphic file storage
ReferenceCounter| Sequential appointment references
AppointmentReminder| Reminder tracking and duplicate prevention

Main Relationships

- A Branch has many DoctorSchedules.
- A Branch has many Appointments.
- A Doctor belongs to a User.
- A Doctor has many DoctorSchedules.
- A Doctor has many Appointments.
- A Doctor may work at multiple branches.
- A Patient belongs to a User.
- A Patient has many Appointments.
- An Appointment belongs to a Patient.
- An Appointment belongs to a Doctor.
- An Appointment belongs to a Branch.
- An Appointment has one MedicalRecord.
- AuditLog uses a polymorphic relationship.
- Attachment uses a polymorphic relationship.

Enumerations

The application uses Enums for fixed values such as:

- User roles
- Appointment statuses
- Doctor specialties
- Weekdays

Appointment Statuses

Status| Description
booked| Appointment successfully booked
arrived| Patient checked in
completed| Appointment completed
cancelled| Appointment cancelled
no_show| Patient missed the appointment

---

5. Authentication and Authorization

Laravel Sanctum is used for token-based API authentication.

Protected endpoints require a valid Bearer Token.

User Roles

Role| Access
Admin| Clinic-wide administrative access
Receptionist| Appointments within the assigned branch
Doctor| Assigned appointments and authorized medical records
Patient| Own appointments and permitted booking operations

Authorization Mechanisms

The application combines:

- Laravel Sanctum
- Policies
- Form Request authorization
- Role checks
- Local Scopes
- Global Scopes

Branch Global Scope

A dedicated Global Scope restricts receptionist appointment queries to the receptionist's assigned branch.

Administrators are intentionally exempt from this restriction.

This helps prevent accidental exposure of appointments belonging to another branch.

HTTP Status Codes

Status| Meaning
200| Success
201| Resource created
204| Successfully deleted
401| Unauthenticated
403| Forbidden
404| Not found
422| Validation failed
500| Internal server error

---

6. Appointment Booking

The booking process uses a dedicated service and database transactions.

Booking Rules

Before creating an appointment, the service validates:

1. The selected doctor exists.
2. The selected branch exists.
3. The doctor works at the selected branch.
4. The doctor is scheduled for the requested day.
5. The appointment falls within the doctor's working hours.
6. The appointment does not overlap an existing active appointment.
7. The patient is eligible for online booking.

Automatic Appointment Reference

Each appointment receives a unique reference number.

Example:

"DAM-2026-000154"

The reference consists of:

- Branch code
- Year
- Sequential number

Reference Generation

The reference is generated by "AppointmentObserver" during the "creating" event.

This ensures the reference is generated regardless of which application service or controller creates the appointment.

The implementation uses a reference counter, database locking, and uniqueness constraints to reduce duplication risks.

Booking Transactions

Booking operations are executed inside database transactions.

The transaction ensures that appointment creation and related database changes remain consistent.

---

7. Medical Records

Each appointment can have an associated medical record.

Medical records contain:

- Diagnosis
- Prescription
- Notes

Medical records can be created and updated only by authorized users.

Doctors cannot modify records belonging to appointments outside their permitted access.

Audit Logging

Medical record modifications are recorded using a polymorphic AuditLog model.

Audit entries include:

- Affected model
- Action
- Authenticated user
- Old values
- New values
- Timestamp

Only changed attributes are recorded.

Why Use Observers?

Audit logging belongs to the model lifecycle rather than a specific Controller.

This prevents the application from depending on every developer remembering to write audit logic whenever a medical record is updated.

The audit system also supports patient-data changes and can be extended to other models.

---

8. Observers, Events, and Listeners

The project distinguishes model lifecycle reactions from explicit business events.

Reaction Table

Component| Trigger| Responsibility| Execution
AppointmentObserver| Creating appointment| Generate unique reference| Synchronous, before insert
AppointmentObserver| Relevant appointment changes| Invalidate availability cache| After commit
MedicalRecordObserver| Medical record changes| Record changed values| Synchronous
PatientObserver| Patient changes| Record changed patient data| Synchronous
DoctorObserver| Relevant doctor changes| Invalidate doctor directory cache| After commit
DoctorScheduleObserver| Schedule changes| Invalidate affected doctor lists| After commit
AppointmentBooked| Successful booking| Publish booking event| After commit
QueueBookingConfirmation| AppointmentBooked| Dispatch confirmation Job| Listener
SendBookingConfirmationJob| Confirmation queued| Log simulated SMS| Background
AppointmentCancelled| Successful cancellation| Publish cancellation event| After commit
LogAppointmentCancellation| AppointmentCancelled| Log cancellation| Listener
SendAppointmentReminderJob| Reminder queued| Log simulated reminder| Background

Observer Responsibilities

Observers handle changes that should occur automatically whenever a model is saved through Eloquent.

Examples:

- Creating appointment references
- Writing audit logs
- Invalidating cached appointment availability
- Invalidating cached doctor lists

Event Responsibilities

Events represent business actions.

Examples:

- Appointment booked
- Appointment cancelled

Listener Responsibilities

Listeners react to events without requiring the booking service to know about notification implementations.

Avoiding Observer Loops

Reference numbers are assigned before the appointment is inserted.

The implementation avoids recursively saving the same model inside its lifecycle event.

When necessary, Eloquent provides methods such as "saveQuietly()" for updates that must not trigger the same event again.

After-Commit Execution

External side effects must not occur before a database transaction successfully commits.

Business events and background notification dispatches use after-commit behavior.

This prevents sending notifications for appointments that were rolled back.

---

9. Bulk Updates and Audit Consistency

Eloquent mass updates performed directly through a query builder do not trigger individual model events.

For example, a direct query-based update may bypass the "updated" Observer.

Architectural Decision

The missed-appointment processing service updates eligible appointments individually through Eloquent models.

This approach preserves model lifecycle reactions.

Advantages

- Observers remain active.
- Related auditing behavior can execute.
- Cache invalidation remains consistent with model events.

Trade-off

Individual model updates require more database operations than a single bulk SQL update.

For larger datasets, chunked processing can be introduced to limit memory usage while preserving per-model behavior.

The choice prioritizes correctness and lifecycle consistency over maximum update speed.

---

10. Appointment Availability Cache

The system provides cached appointment availability for doctors.

Endpoint

"GET /api/availability"

Required Parameters

- "doctor_id"
- "branch_id"
- "date"

Example

"GET /api/availability?doctor_id=1&branch_id=1&date=2026-11-25"

Availability Calculation

The service:

1. Retrieves doctor schedules for the requested day and branch.
2. Retrieves overlapping appointments.
3. Calculates available time slots.
4. Excludes conflicting appointments.
5. Excludes past slots.
6. Caches the calculated result.

Cache Duration

10 minutes.

Cache Key Example

"availability:doctor:1:branch:1:date:2026-11-25"

Cache Invalidation

The AppointmentObserver invalidates relevant availability cache entries when appointments are created, cancelled, or otherwise changed in a way that affects availability.

Invalidation happens after the database transaction commits.

Booking Safety

Cached availability is used to improve read performance.

Actual booking validation is performed against database records inside the booking workflow.

Cache results are not treated as the final authority for appointment creation.

---

11. Doctor Directory Cache

The system provides a filtered and cached doctor directory.

Endpoint

"GET /api/doctors"

Filters

- "branch_id"
- "specialty"
- "per_page"

Example

"GET /api/doctors?branch_id=1&specialty=cardiology"

Cache Duration

10 minutes.

Cache Key Example

"doctors:branch:1:specialty:cardiology"

Cache Invalidation

The doctor directory cache is invalidated when relevant doctor or schedule information changes.

Examples include:

- Doctor name changes
- Doctor specialty changes
- Doctor schedule creation
- Doctor schedule modification
- Doctor schedule deletion

The application uses DoctorObserver and DoctorScheduleObserver for these operations.

Results are returned using API Resources and pagination.

---

12. Polymorphic Attachments

The application supports secure file attachments through an Eloquent polymorphic relationship.

Supported Parent Models

- Appointment
- MedicalRecord

Supported Formats

- PDF
- JPG
- JPEG
- PNG
- DOC
- DOCX

Maximum File Size

5 MB.

Stored Information

Each attachment stores:

- Parent model type
- Parent model ID
- Uploading user ID
- Original filename
- Storage path
- MIME type
- File size
- Timestamps

File Storage

Attachments are stored using Laravel's private local storage disk.

The API controls access to files rather than exposing unrestricted public download URLs.

Attachment Authorization

- Administrators can access authorized attachments.
- Receptionists are limited to permitted appointment attachments within their branch.
- Doctors can access attachments associated with their authorized appointments.
- Patients can access permitted attachments under the implemented read-only rules.

Attachment Endpoints

Method| Endpoint| Description
GET| "/api/attachments/{type}/{id}"| List attachments
POST| "/api/attachments/{type}/{id}"| Upload file
GET| "/api/attachment-files/{attachment}"| Download file
DELETE| "/api/attachment-files/{attachment}"| Delete file

Supported parent type values:

- "appointment"
- "medical-record"

Attachment upload and listing were manually tested during development.

---

13. API Endpoints

The API includes the following routes.

Method| Endpoint| Description
GET| "/api/me"| Authenticated user
GET| "/api/appointments"| List and filter appointments
GET| "/api/appointments/{id}"| Appointment details
POST| "/api/appointments"| Book appointment
PATCH| "/api/appointments/{id}/arrive"| Register patient arrival
PATCH| "/api/appointments/{id}/cancel"| Cancel appointment
GET| "/api/appointments/{id}/medical-record"| View medical record
POST| "/api/appointments/{id}/medical-record"| Create medical record
PATCH| "/api/medical-records/{id}"| Update medical record
GET| "/api/medical-records/{id}/audit-logs"| View audit history
GET| "/api/availability"| Available appointment slots
GET| "/api/doctors"| Filtered doctor directory
GET| "/api/attachments/{type}/{id}"| List attachments
POST| "/api/attachments/{type}/{id}"| Upload attachment
GET| "/api/attachment-files/{attachment}"| Download attachment
DELETE| "/api/attachment-files/{attachment}"| Delete attachment

The exact route definitions can be inspected with:

"php artisan route:list --path=api"

The API uses Sanctum authentication, Form Requests, Policies, Services, and API Resources.

---

14. SMS Service Abstraction

The system does not depend on a real SMS provider.

An interface-based design allows notification implementations to be replaced without changing booking business logic.

SmsSender Interface

Defines the contract for sending SMS messages.

LogSmsSender

Implements the interface by writing notification messages to Laravel logs.

Dependency Injection

The Laravel Service Container binds SmsSender to LogSmsSender.

Benefits

- Loose coupling
- Easy replacement with a real SMS provider
- Better testability
- Clear separation of responsibilities
- Compatibility with queued background Jobs

---

15. Custom Artisan Commands

The project implements four custom Artisan Commands.

Each command delegates business logic to a reusable service.

Command Summary

Command| Options| Schedule
"clinic:send-reminders"| "--date", "--branch", "--dry-run"| Daily, 18:00
"clinic:mark-missed"| "--as-of", "--branch", "--grace", "--dry-run", "--fail"| Every 5 minutes
"clinic:lift-bans"| "--as-of", "--dry-run", "--fail"| Hourly
"clinic:monthly-report"| "--month", "--branch", "--dry-run", "--fail"| First day of each month, 02:00

All schedules use the "Asia/Damascus" timezone.

Command 1: Appointment Reminders

Command:

"php artisan clinic:send-reminders"

Preview:

"php artisan clinic:send-reminders --dry-run"

This command identifies appointments scheduled for the following day.

Reminder records are stored in the "appointment_reminders" table.

A database uniqueness constraint prevents multiple reminder records for the same appointment and reminder date.

Notification processing is delegated to SendAppointmentReminderJob.

The command is designed to be idempotent.

Running it repeatedly should not generate duplicate reminders for appointments already processed.

Command 2: Mark Missed Appointments

Command:

"php artisan clinic:mark-missed"

Preview:

"php artisan clinic:mark-missed --dry-run"

The command identifies appointments that have passed their scheduled time and grace period without patient arrival.

Eligible appointments are marked as "no_show".

If a patient has three no-shows within 30 days, the system applies a 30-day online-booking restriction.

Command 3: Lift Expired Booking Restrictions

Command:

"php artisan clinic:lift-bans"

Preview:

"php artisan clinic:lift-bans --dry-run"

The command identifies patients whose online-booking restrictions have expired.

It removes the expired restriction and restores booking eligibility.

Command 4: Monthly Branch Reports

Command:

"php artisan clinic:monthly-report"

The command generates monthly reports containing:

- Total appointments
- No-show counts
- Doctor workload statistics
- Branch-level activity

Reports are generated as CSV files and saved in application storage.

Command Design

Custom commands are intentionally thin.

They:

1. Read input arguments and options.
2. Validate input.
3. Call a dedicated service.
4. Display meaningful output.
5. Return an appropriate exit status.

This keeps business logic reusable from both CLI and API contexts.

Inspect Command Options

"php artisan help clinic:send-reminders"

"php artisan help clinic:mark-missed"

"php artisan help clinic:lift-bans"

"php artisan help clinic:monthly-report"

---

16. Task Scheduler

All scheduled operations are configured in:

"routes/console.php"

Timezone

"Asia/Damascus"

Scheduler Configuration

Task| Frequency| Overlap Protection| Single-Server Protection| Reason
Send reminders| Daily at 18:00| Enabled| Enabled| Remind patients before the following day
Mark missed appointments| Every 5 minutes| Enabled| Enabled| Process overdue appointments promptly
Lift booking restrictions| Hourly| Enabled| Enabled| Restore eligibility after expiration
Monthly reports| First day, 02:00| Enabled| Enabled| Generate reports outside peak hours

withoutOverlapping()

Prevents overlapping scheduled executions while a previous task holds its scheduler lock.

This is particularly useful for long-running operations.

onOneServer()

Prevents the same scheduled task from executing simultaneously on multiple application servers, provided they use a suitable shared cache backend.

Failure Handling

Scheduled commands write output to task-specific logs.

Failure callbacks record errors so that failures are visible to administrators.

Scheduler Logs

The configured scheduler uses separate logs for reminders, missed appointments, patient restrictions, and monthly reports.

Application errors are also available through Laravel logging.

Check Scheduler Configuration

"php artisan schedule:list"

This displays registered scheduled commands and their next execution times.

The output depends on the current date and server configuration.

Run Due Tasks Manually

"php artisan schedule:run"

Local Scheduler

"php artisan schedule:work"

Production Cron Entry

On Linux, add the following cron entry:

"* * * * * cd /path/to/balsam-clinic-api && php artisan schedule:run >> /dev/null 2>&1"

The system cron invokes Laravel's scheduler every minute.

Failure Simulation

The project supports failure simulation using a command option.

Example:

"php artisan clinic:mark-missed --fail"

The expected result is a non-zero exit code and a logged error.

To verify scheduler failure callbacks specifically, the failing command must execute through the Scheduler.

---

17. Queues and Background Jobs

The project uses background Jobs for non-blocking notification processing.

Queue Priorities

Queue| Priority| Tasks
high| High| Appointment booking confirmations
default| Medium| General background Jobs
low| Low| Appointment reminders

Using separate queues prevents low-priority reminder processing from unnecessarily delaying booking confirmations.

Local Windows Development

The local Windows/XAMPP environment uses database queues.

Environment variables:

"QUEUE_CONNECTION=database"

"CACHE_STORE=database"

Start a worker:

"php artisan queue:work database --queue=high,default,low --tries=3"

After-Commit Safety

Queued notifications must not run before the associated database transaction commits.

Booking and cancellation workflows use after-commit event handling.

Failed Jobs

Laravel provides failed-job tracking and logging.

Failed background tasks should be inspected and retried only when their behavior is safe to repeat.

---

18. Redis Integration

Redis is used as the intended shared cache and queue backend.

Redis Client

The project uses the Predis package.

Installed package:

"predis/predis"

Redis Configuration

For an environment with a running Redis server:

"QUEUE_CONNECTION=redis"

"CACHE_STORE=redis"

"REDIS_CLIENT=predis"

"REDIS_HOST=127.0.0.1"

"REDIS_PASSWORD=null"

"REDIS_PORT=6379"

Cache Implementation

The application uses Laravel's Cache abstraction.

The same availability and doctor directory cache services can use database-backed caching locally and Redis-backed caching when the Redis configuration is enabled.

Redis Verification

Redis connectivity and cache read/write operations were successfully verified using a GitHub Actions workflow on Ubuntu.

The verification used a Redis service container and the Predis client.

GitHub Actions verification date: November 24, 2026.

Result: PASSED.

---

19. Laravel Horizon

Laravel Horizon is installed to manage and monitor Redis-backed queue workers.

Installed package:

"laravel/horizon"

Horizon Configuration

Configuration file:

"config/horizon.php"

The project defines supervisors for:

- high
- default
- low

Responsibilities

Horizon provides:

- Redis queue worker supervision
- Queue monitoring
- Job execution visibility
- Worker process management
- Queue prioritization through supervisor configuration

Start Horizon

On a compatible Linux environment with Redis running:

"php artisan horizon"

Check Horizon Status

"php artisan horizon:status"

Stop Horizon Gracefully

"php artisan horizon:terminate"

Verified Execution

A GitHub Actions workflow successfully verified Horizon on Ubuntu.

The workflow tested:

1. Redis cache write and read operations.
2. Laravel Horizon startup.
3. Redis queue connection.
4. Dispatching a Job to the "high" queue.
5. Execution of the queued Job.

The workflow completed successfully on November 24, 2026.

Verification Environment

Component| Value
Platform| GitHub Actions
Operating system| Ubuntu
PHP| 8.3
Redis| Redis 7
Redis client| Predis
Queue connection| redis
Cache store| redis
Queue supervisor| Laravel Horizon
Workflow result| PASSED

Local Development Note

The Windows/XAMPP development environment continues to use database-backed queues and cache.

Horizon is intended to run in a compatible Linux environment.

The successful Ubuntu verification demonstrates that the Redis cache, Redis queue, and Horizon processing path can operate together.

It does not replace full production deployment testing or end-to-end tests of every application Job.

---

20. GitHub Actions CI Verification

The project includes an automated workflow for Redis and Horizon verification.

Workflow File

".github/workflows/redis-horizon.yml"

Workflow Name

Redis and Horizon Verification

Trigger

The workflow runs when changes are pushed to the "main" branch and can also be triggered manually.

Verification Steps

1. Check out the repository.
2. Install PHP and the necessary extensions.
3. Install Composer dependencies.
4. Prepare the Laravel application.
5. Start the Redis service.
6. Verify Redis cache operations.
7. Start Laravel Horizon.
8. Dispatch a test Job to the Redis "high" queue.
9. Verify successful Job execution.

Actual Verification Result

Date: November 24, 2026

Branch: main

Result: SUCCESS

The GitHub Actions run completed successfully with a green status indicator.

Significance

This workflow provides reproducible evidence that the project's Redis and Horizon integration works in a compatible Linux environment.

---

21. Command Testing Scenarios

Scenario 1: Reminder Dry Run

Command:

"php artisan clinic:send-reminders --dry-run"

Expected result:

- Eligible appointments are displayed.
- No notifications are dispatched.
- No reminder records are created by the preview operation.

Scenario 2: First Reminder Execution

Command:

"php artisan clinic:send-reminders"

Expected result:

- Eligible reminders are recorded.
- Reminder Jobs are dispatched.
- Queue workers process pending notifications.

Scenario 3: Repeat Reminder Execution

Command:

"php artisan clinic:send-reminders"

Expected result:

- Previously recorded reminders are skipped.
- Duplicate notifications are prevented.

In a Redis environment, successful background execution can be monitored through Horizon.

Scenario 4: Missed Appointments

Command:

"php artisan clinic:mark-missed --dry-run"

Expected result:

- Eligible overdue appointments are displayed.

Execute:

"php artisan clinic:mark-missed"

Expected result:

- Eligible appointments become "no_show".
- Patients with three no-shows within 30 days receive a temporary online-booking restriction.

Scenario 5: Expired Booking Restrictions

Command:

"php artisan clinic:lift-bans --dry-run"

Expected result:

- Patients with expired restrictions are identified.

Execute:

"php artisan clinic:lift-bans"

Expected result:

- Expired restrictions are removed.

Scenario 6: Monthly Reports

Command:

"php artisan clinic:monthly-report"

Expected result:

- Monthly clinic statistics are calculated.
- CSV reports are created in application storage.

Scenario 7: Failure Simulation

Command:

"php artisan clinic:mark-missed --fail"

Expected result:

- A simulated failure occurs.
- The command exits with a non-zero status.
- Error details appear in Laravel logs.

Scenario 8: Schedule Inspection

Command:

"php artisan schedule:list"

Expected result:

- All four scheduled commands are listed.
- Their configured frequencies are visible.
- Laravel displays the next execution time for each task.

The actual "schedule:list" output should be captured directly from the application when presenting execution evidence.

---

22. Installation and Setup

Requirements

- PHP compatible with the project's Composer dependencies
- Composer
- MySQL
- Laravel-required PHP extensions
- Redis for Redis-backed caching and queues
- Compatible Linux environment for Horizon runtime

Step 1: Clone the Repository

"git clone https://github.com/NourDbe/balsam-clinic-api"

Open the Laravel project directory containing the "artisan" file.

Step 2: Install Dependencies

"composer install"

On Windows, if Horizon's Linux-specific extension checks prevent dependency installation, the following command can be used for local development:

"composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix"

This bypasses extension checks during installation but does not enable Horizon to run on unsupported Windows environments.

Step 3: Create the Environment File

Copy ".env.example" to ".env".

PowerShell:

"Copy-Item .env.example .env"

Step 4: Configure MySQL

Example configuration:

"DB_CONNECTION=mysql"

"DB_HOST=127.0.0.1"

"DB_PORT=3306"

"DB_DATABASE=balsam-clinic"

"DB_USERNAME=root"

"DB_PASSWORD="

Create the database in MySQL before running migrations.

The hyphen in the database name must be handled correctly when writing a manual SQL statement.

Step 5: Configure Local Queues and Cache

For Windows/XAMPP development:

"QUEUE_CONNECTION=database"

"CACHE_STORE=database"

Step 6: Generate Application Key

"php artisan key:generate"

Step 7: Run Database Migrations

"php artisan migrate"

Step 8: Seed Demonstration Data

"php artisan db:seed"

For a new empty database, migrations and seeding can also be run together:

"php artisan migrate --seed"

Step 9: Start the Application

"php artisan serve --port=8001"

Local base URL:

"http://127.0.0.1:8001/api"

Step 10: Start Queue Processing

"php artisan queue:work database --queue=high,default,low"

Step 11: Start the Scheduler

In a separate terminal:

"php artisan schedule:work"

Step 12: Verify Installation

"php artisan migrate:status"

"php artisan route:list --path=api"

"php artisan schedule:list"

"php artisan test"

Important Warning

Do not run "migrate:fresh" against a database containing important data.

The command drops existing database tables.

---

23. Database Seeder

The project includes a Database Seeder for testing.

The Seeder creates demonstration data such as:

- Clinic branches
- Doctors
- Doctor working schedules
- Patients
- Users with different roles
- Appointments
- Future appointments
- Previously missed appointments
- Medical records
- API authentication tokens

The demonstration data supports testing booking workflows, reminders, no-show restrictions, and audit logging.

Tokens generated for testing must be treated as credentials and must not be committed to the repository.

---

24. Automated Testing

The project contains Laravel Feature Tests.

Run All Tests

"php artisan test"

Run API Feature Tests

"php artisan test --filter=BalsamApiTest"

Latest Local Test Result

7 tests passed.

The tests exercise core API behavior, including:

- Appointment reference generation
- Duplicate booking rejection
- Authentication requirements
- Branch authorization restrictions
- Medical record audit logging
- Additional API test scenarios

Additional Manual Verification

Manual development verification included:

- MySQL migrations
- Seeder execution
- Appointment booking
- Appointment status updates
- Scheduler command execution
- Attachment upload and listing
- API requests using PowerShell

Redis and Horizon Verification

Redis and Horizon were separately verified through a successful GitHub Actions workflow on Ubuntu.

Test Coverage Note

Passing tests demonstrate successful execution of the implemented scenarios.

They do not guarantee complete coverage of every permission rule, file operation, deployment configuration, or edge case.

---

25. Postman Collection

A Postman Collection is included with the project for API demonstration.

The collection is intended to cover:

1. Appointment booking.
2. Automatic appointment reference generation.
3. Booking through different authorized roles.
4. Medical record updates.
5. Viewing old and new audit values.
6. Appointment cancellation.
7. Availability before and after cancellation.
8. Branch access restrictions.
9. Unauthorized medical record modifications.
10. Booking outside doctor schedules.
11. Overlapping appointment rejection.
12. Online-booking restrictions.

Additional available endpoints include doctor filtering and attachment operations.

Authentication

Protected endpoints require:

"Authorization: Bearer YOUR_TOKEN"

Base URL

"http://127.0.0.1:8001/api"

Change the port according to the running Laravel server.

Security

Do not include real authentication tokens or sensitive patient information in the committed collection.

The committed collection should be reviewed to ensure all required scenarios and variables are present.

---

26. Project Directory Structure

app/

- Console/Commands
- Enums
- Events
- Http/Controllers/Api
- Http/Requests
- Http/Resources
- Jobs
- Listeners
- Models
- Models/Scopes
- Observers
- Policies
- Providers
- Services

config/

- horizon.php

database/

- migrations
- seeders

routes/

- api.php
- console.php

storage/

- app
- logs

tests/

- Feature

.github/

- workflows/redis-horizon.yml

Additional project files:

- composer.json
- composer.lock
- .env.example
- README.md
- Postman Collection

---

27. Security and Reliability

The application includes the following security and reliability mechanisms:

- Sanctum token authentication
- Role-based authorization
- Branch-scoped appointment access
- Doctor-specific medical record permissions
- Form Request validation
- Appointment overlap checks
- Booking restrictions for repeated no-shows
- Database transactions
- Unique reference constraints
- Polymorphic audit logging
- Private attachment storage
- File type and size validation
- After-commit event processing
- Scheduler overlap protection
- Single-server scheduler execution
- Queue retry configuration
- Failure logging

Production Environment

For a production deployment:

"APP_ENV=production"

"APP_DEBUG=false"

Environment secrets and authentication tokens must remain outside source control.

Production infrastructure requires properly configured database access, Redis, queue supervision, and scheduler execution.

---

28. Verification Summary

Component| Verification Status
Laravel API| Implemented
MySQL database| Used in local development
Database migrations| Verified locally
Database Seeder| Verified locally
Sanctum authentication| Implemented and tested
Appointment booking| Implemented and tested
Automatic appointment references| Implemented and tested
Medical record audit logging| Implemented and tested
Model Observers| Implemented
Events and Listeners| Implemented
Artisan Commands| Implemented and manually exercised
Task Scheduler| Configured and inspected
Availability caching| Implemented
Doctor directory caching| Implemented
Polymorphic attachments| Implemented and manually tested
Redis package| Installed
Laravel Horizon| Installed and configured
Redis cache read/write| Verified on Ubuntu through GitHub Actions
Redis queue processing| Verified on Ubuntu through GitHub Actions
Horizon startup and Job execution| Verified on Ubuntu through GitHub Actions
Laravel Feature Tests| 7 passed
Full production deployment| Not performed

Important Verification Distinction

The GitHub Actions workflow successfully verified the Redis cache, Redis queue, and Horizon integration using a dedicated smoke-test Job.

This verifies the infrastructure integration path.

It does not imply that every application-specific reminder Job, cache invalidation scenario, or production deployment configuration was tested through Redis.

---

29. GitHub and Version Control

Git is used to track the project's development.

Development was divided into commits covering:

- Initial Laravel setup
- Database models and migrations
- Authentication and authorization
- Appointment booking
- Medical records
- Model Observers
- Audit logging
- Events and Listeners
- Background Jobs
- Artisan Commands
- Task scheduling
- Seeders
- Feature Tests
- Redis integration
- Horizon configuration
- Availability caching
- Doctor directory caching
- Polymorphic attachments
- GitHub Actions verification
- Project documentation

The repository's commit history demonstrates incremental development.

Useful Git Commands

"git status"

"git log --oneline"

"git push origin main"

---

30. Conclusion

Balsam Clinic Management API demonstrates a multi-branch medical management backend built with Laravel.

The project focuses on a clean separation of business logic, model lifecycle reactions, background processing, and recurring administrative automation.

It implements:

- Secure appointment management
- Automatic reference generation
- Polymorphic medical audit history
- Role-based authorization
- Doctor availability and directory caching
- Events and Listeners
- Background Jobs
- Four custom Artisan Commands
- Scheduled operations with execution protections
- Polymorphic attachments
- Redis and Horizon integration

The application was developed locally using Windows/XAMPP with MySQL and database-backed queues.

Redis caching, Redis queue processing, and Laravel Horizon were successfully verified in a compatible Ubuntu environment using GitHub Actions on November 24, 2026.

The latest reported Laravel Feature Test result was 7 tests passed.

---

