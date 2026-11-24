Balsam Clinic Management API

A multi-branch clinic management REST API developed with Laravel.

The system manages clinic branches, doctors, schedules, patients, appointments, medical records, and audit history. It also provides background jobs and automated administrative tasks.

1. Technologies

- PHP & Laravel
- MySQL
- Laravel Sanctum
- Eloquent ORM & Model Observers
- Laravel Events & Listeners
- Artisan Commands & Task Scheduler
- Database Queues
- Redis (prepared)
- Laravel Horizon (configured)
- PHPUnit Feature Tests

2. Main Features

Appointment Management

- Book appointments with automatic unique reference numbers.
- Validate doctors' working schedules.
- Prevent overlapping appointments.
- Register patient arrival and cancel appointments.
- Filter appointments by branch, doctor, specialty, date, and status.
- Paginate API results.

Medical Records

- Create and update diagnoses and prescriptions.
- Restrict medical record updates to authorized doctors.
- Track changed fields using polymorphic Audit Logs.
- Store previous and new values, user ID, and modification time.

Automated Tasks

- Send appointment reminders.
- Mark unattended appointments as no-shows.
- Restrict online booking after three no-shows within 30 days.
- Lift expired booking restrictions.
- Generate monthly branch reports in CSV format.

3. Installation

Requirements: PHP and Composer compatible with the installed Laravel version, MySQL, and the required PHP extensions.

Clone the repository:

"git clone <https://github.com/NourDbe/balsam-clinic-api>"

"cd balsam-clinic-api"

Install dependencies:

"composer install"

For local development on Windows, Horizon's Linux-specific extension requirements may require a compatible Linux environment. The existing Windows development environment uses database-backed queues.

Copy ".env.example" to ".env" and generate an application key:

"php artisan key:generate"

Configure the database:

Variable| Example value
DB_CONNECTION| mysql
DB_HOST| 127.0.0.1
DB_PORT| 3306
DB_DATABASE| balsam-clinic
DB_USERNAME| root
DB_PASSWORD| Local MySQL password

Run migrations and seeders:

"php artisan migrate --seed"

Start Laravel:

"php artisan serve"

4. Authentication and Roles

Laravel Sanctum protects the API using Bearer Tokens.

Role| Access
Admin| Manage clinic data across branches
Receptionist| Manage appointments within their assigned branch
Doctor| Access assigned appointments and authorized medical records
Patient| Book and access their own appointments

Demo users and local testing tokens are created by the Database Seeder.

Tokens must not be committed to GitHub.

5. API Endpoints

All endpoints require Sanctum authentication.

Method| Endpoint| Description
GET| "/api/me"| Current authenticated user
GET| "/api/appointments"| Paginated appointments
GET| "/api/appointments/{id}"| Appointment details
POST| "/api/appointments"| Create an appointment
PATCH| "/api/appointments/{id}/arrive"| Register patient arrival
PATCH| "/api/appointments/{id}/cancel"| Cancel an appointment
GET| "/api/appointments/{id}/medical-record"| View medical record
POST| "/api/appointments/{id}/medical-record"| Create medical record
PATCH| "/api/medical-records/{id}"| Update medical record
GET| "/api/medical-records/{id}/audit-logs"| Paginated audit history

API responses use Laravel API Resources.

Form Requests handle input validation and authorization. Invalid data returns HTTP 422; unauthorized actions return HTTP 403.

6. Architecture

The application separates responsibilities between Controllers, Services, Models, Observers, Events, Listeners, and Jobs.

Controllers accept requests and return API Resources.

Services contain the business logic.

Observers react to Eloquent model lifecycle events.

Events represent business actions, while Listeners handle their consequences.

Jobs execute background work using queues.

Observers and Events

Component| Trigger| Responsibility| Execution
AppointmentObserver| Appointment creation| Generate reference number| Before insert
AppointmentObserver| Availability-related changes| Invalidate availability cache| After commit
MedicalRecordObserver| Medical record update| Record changed fields| Synchronous
PatientObserver| Patient update| Record changed patient fields| Synchronous
AppointmentBooked| Successful booking| Notify listeners| After commit
QueueBookingConfirmation| AppointmentBooked| Dispatch confirmation Job| Synchronous dispatch
SendBookingConfirmationJob| Queued confirmation| Write demo SMS to log| Background
AppointmentCancelled| Successful cancellation| Notify listeners| After commit
LogAppointmentCancellation| AppointmentCancelled| Log cancellation| Synchronous
SendAppointmentReminderJob| Scheduled reminder| Write demo reminder to log| Background

Reference numbers are generated using a branch/year counter protected by database locking and uniqueness constraints.

Audit Logs use a polymorphic relationship and store only fields that changed.

Eloquent mass updates do not execute per-model Observers. The no-show and ban-expiration services therefore save affected models individually. This has a higher processing cost but preserves Observer-based audit behavior.

External jobs are dispatched after database commit to avoid processing data from rolled-back transactions.

7. Artisan Commands

Four custom Artisan Commands are implemented.

Command| Options| Schedule
"clinic:send-reminders"| "--date", "--branch", "--dry-run"| Daily, 18:00
"clinic:mark-missed"| "--as-of", "--branch", "--grace", "--dry-run", "--fail"| Every 5 minutes
"clinic:lift-bans"| "--as-of", "--dry-run", "--fail"| Hourly
"clinic:monthly-report"| "--month", "--branch", "--dry-run", "--fail"| First day of each month, 02:00

All scheduled times use "Asia/Damascus".

The reminders command runs at 18:00 so patients can be notified about the next day's appointments.

Missed appointments are checked every five minutes to keep attendance status up to date.

Expired restrictions are checked hourly.

Monthly reports run early on the first day of each month to summarize the previous month.

8. Task Scheduling

Scheduled commands are defined in "routes/console.php".

Scheduling protections:

Command| withoutOverlapping| onOneServer| Log
Reminders| 30 minutes| Yes| clinic-reminders.log
Mark missed| 15 minutes| Yes| clinic-missed.log
Lift bans| 15 minutes| Yes| clinic-bans.log
Monthly report| 120 minutes| Yes| clinic-reports.log

The scheduler uses a shared cache store for coordination. A shared database cache is used in local development. Redis can be used in a compatible deployment.

Each command writes output to its own log. Failure callbacks record errors in the application log.

Inspect scheduled commands:

"php artisan schedule:list"

Run due commands:

"php artisan schedule:run"

Run the scheduler locally:

"php artisan schedule:work"

Production Linux cron entry:

"* * * * * cd /path/to/balsam-clinic-api && php artisan schedule:run >> /dev/null 2>&1"

Scheduler Verification

The "schedule:list" output must be captured from the deployed or testing environment to verify registered commands and their next execution times.

The "--fail" option can simulate command failures and verify scheduler failure logging.

9. Queues, Redis, and Horizon

Background messages use separate queues:

Queue| Priority| Tasks
high| High| Booking confirmations
default| Normal| General background processing
low| Low| Appointment reminders

The default local development environment uses database queues.

Local worker:

"php artisan queue:work database --queue=high,default,low --tries=3"

Redis support is provided through Predis.

For a compatible Linux environment with an accessible Redis server, configure:

"QUEUE_CONNECTION=redis"

"CACHE_STORE=redis"

"REDIS_CLIENT=predis"

"REDIS_HOST=127.0.0.1"

"REDIS_PORT=6379"

Then run:

"php artisan optimize:clear"

"php artisan horizon"

Horizon supervisors are configured in "config/horizon.php" with separate worker pools for each queue.

Verification status: Horizon has been installed and configured, but an actual Redis-backed Horizon worker session has not yet been verified in the Windows/XAMPP development environment.

No real SMS gateway is required. "SmsSender" is bound to "LogSmsSender", which writes demo messages to Laravel logs.

10. Command Test Scenarios

Reminder idempotency

1. Run "php artisan clinic:send-reminders --dry-run".
2. Run "php artisan clinic:send-reminders".
3. Start a queue worker to process reminder jobs.
4. Repeat the reminders command.
5. Confirm that already recorded reminders are not queued again.

No-show handling

1. Prepare a patient with two previous no-shows.
2. Run "php artisan clinic:mark-missed --dry-run".
3. Run "php artisan clinic:mark-missed".
4. Verify that the third no-show creates a 30-day online booking restriction.
5. Repeat the command to verify that already processed appointments are skipped.

Expired booking restrictions

1. Create a patient with an expired booking restriction.
2. Run "php artisan clinic:lift-bans --dry-run".
3. Run "php artisan clinic:lift-bans".
4. Verify that the restriction has been removed.

Monthly reports

Run:

"php artisan clinic:monthly-report --month=2026-11"

Reports are stored in the configured local storage disk under the "reports" directory.

Failure simulation

Run:

"php artisan clinic:mark-missed --fail"

The command should return a non-zero exit status and log the simulated failure.

11. Testing

Run automated Feature Tests:

"php artisan test --filter=BalsamApiTest"

Run all tests:

"php artisan test"

The automated tests cover appointment creation, unique reference number generation, duplicate-booking rejection, authentication, branch authorization, and medical record audit history.

A Postman Collection is included in the "postman" directory.

Use a separate testing database when running destructive migration commands.

12. Current Limitations and Remaining Verification

- Redis connectivity and live Horizon worker execution still require verification on a compatible Linux environment.
- The configured availability cache invalidation must be verified against an implemented Redis-backed availability lookup.
- Doctor-list caching, a dedicated Global Scope for receptionists, and polymorphic attachments require a final requirement check.
- Centralized API exception formatting and complete authorization edge cases require final verification.
- End-to-end SMS delivery is simulated with log output rather than an external provider.
- Strong delivery idempotency with a real SMS provider would require provider-supported idempotency or additional delivery reconciliation.

13. Project Structure

Directory| Purpose
"app/Models"| Eloquent models and relationships
"app/Enums"| Status, specialty, weekday, and role enums
"app/Observers"| Automatic model reactions
"app/Events"| Business events
"app/Listeners"| Event handling
"app/Jobs"| Background tasks
"app/Services"| Business logic
"app/Console/Commands"| Artisan commands
"app/Http/Requests"| Validation and authorization
"app/Http/Resources"| API response formatting
"routes/api.php"| API endpoints
"routes/console.php"| Scheduler configuration
"database/seeders"| Demonstration data
"tests/Feature"| Feature tests
"postman"| API request collection

14. Security

- API endpoints are protected using Laravel Sanctum.
- Branch-level and doctor-level access restrictions are applied.
- Medical record changes are audited.
- Appointment creation checks schedules, booking conflicts, and online booking restrictions.
- Secrets, environment files, and API tokens are excluded from Git.

---

Developed as a Laravel backend architecture and automation project.
