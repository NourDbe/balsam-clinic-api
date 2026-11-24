<?php

namespace Tests\Feature;

use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Enums\WeekDay;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BalsamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Doctor $doctor;
    private Patient $patient;
    private Branch $branch;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->branch = Branch::create([
            'name' => 'Test Damascus', 'code' => 'DAM',
            'city' => 'Damascus',
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN, 'branch_id' => null,
        ]);

        $this->doctor = Doctor::create([
            'full_name' => 'Dr Test',
            'specialty' => Specialty::GENERAL,
        ]);

        $this->patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999123456',
        ]);

        $this->date = CarbonImmutable::now('Asia/Damascus')
            ->next('Sunday')->addWeek()->toDateString();

        DoctorSchedule::create([
            'doctor_id' => $this->doctor->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => WeekDay::SUNDAY,
            'start_time' => '09:00:00',
            'end_time' => '16:00:00',
        ]);
    }

    private function bookingData(): array
    {
        return [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'branch_id' => $this->branch->id,
            'date' => $this->date,
            'start_time' => '10:00',
        ];
    }

    public function test_booking_generates_a_reference_number(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/appointments', $this->bookingData())
            ->assertCreated()
            ->assertJsonPath('data.status', 'booked');

        $reference = $response->json('data.reference_number');

        $this->assertMatchesRegularExpression(
            '/^DAM-\d{4}-\d{6,}$/', $reference
        );
        $this->assertDatabaseHas('appointments', [
            'reference_number' => $reference,
        ]);
    }

    public function test_double_booking_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/appointments', $this->bookingData())
            ->assertCreated();

        $this->postJson('/api/appointments', $this->bookingData())
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_time');
    }

    public function test_booking_requires_authentication(): void
    {
        $this->postJson('/api/appointments', $this->bookingData())
            ->assertUnauthorized();
    }

    public function test_reception_cannot_book_for_another_branch(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Test Aleppo', 'code' => 'ALP',
            'city' => 'Aleppo',
        ]);

        $reception = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($reception);

        $this->postJson('/api/appointments', $this->bookingData())
            ->assertForbidden();
    }

    public function test_medical_record_update_creates_audit_log(): void
    {
        Sanctum::actingAs($this->admin);

        $booking = $this->postJson('/api/appointments', $this->bookingData())
            ->assertCreated();

        $appointmentId = $booking->json('data.id');

        $this->patchJson("/api/appointments/{$appointmentId}/arrive")
            ->assertOk();

        $doctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);
        $this->doctor->update(['user_id' => $doctorUser->id]);

        Sanctum::actingAs($doctorUser);

        $created = $this->postJson(
            "/api/appointments/{$appointmentId}/medical-record",
            ['diagnosis' => 'Migraine', 'prescription' => 'Drug A']
        )->assertCreated();

        $recordId = $created->json('data.id');

        $this->patchJson("/api/medical-records/{$recordId}", [
            'diagnosis' => 'Tension Headache',
        ])->assertOk();

        $this->getJson("/api/medical-records/{$recordId}/audit-logs")
            ->assertOk()
            ->assertJsonPath('data.0.old_values.diagnosis', 'Migraine')
            ->assertJsonPath('data.0.new_values.diagnosis', 'Tension Headache');
    }
}
