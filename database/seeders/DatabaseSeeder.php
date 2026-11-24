<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Branches
        $damascus = Branch::updateOrCreate(
            ['code' => 'DAM'],
            [
                'name' => 'Balsam Damascus',
                'city' => 'Damascus',
                'address' => 'Damascus, Syria',
            ]
        );

        $aleppo = Branch::updateOrCreate(
            ['code' => 'ALP'],
            [
                'name' => 'Balsam Aleppo',
                'city' => 'Aleppo',
                'address' => 'Aleppo, Syria',
            ]
        );

        $latakia = Branch::updateOrCreate(
            ['code' => 'LAT'],
            [
                'name' => 'Balsam Latakia',
                'city' => 'Latakia',
                'address' => 'Latakia, Syria',
            ]
        );

        // Users
        $admin = User::updateOrCreate(
            ['email' => 'admin@balsam.test'],
            [
                'name' => 'Balsam Admin',
                'password' => Str::random(40),
                'role' => UserRole::ADMIN,
                'branch_id' => null,
            ]
        );

        $reception = User::updateOrCreate(
            ['email' => 'reception@balsam.test'],
            [
                'name' => 'Damascus Reception',
                'password' => Str::random(40),
                'role' => UserRole::RECEPTIONIST,
                'branch_id' => $damascus->id,
            ]
        );

        $doctorUser = User::updateOrCreate(
            ['email' => 'doctor@balsam.test'],
            [
                'name' => 'Dr. Ahmad',
                'password' => Str::random(40),
                'role' => UserRole::DOCTOR,
                'branch_id' => null,
            ]
        );

        $patientUser = User::updateOrCreate(
            ['email' => 'patient@balsam.test'],
            [
                'name' => 'Sara Ali',
                'password' => Str::random(40),
                'role' => UserRole::PATIENT,
                'branch_id' => null,
            ]
        );

        // Doctors
        $doctor = Doctor::updateOrCreate(
            ['full_name' => 'Dr. Ahmad'],
            [
                'user_id' => $doctorUser->id,
                'specialty' => Specialty::CARDIOLOGY,
                'phone' => '0999111222',
            ]
        );

        $doctorTwo = Doctor::updateOrCreate(
            ['full_name' => 'Dr. Lina'],
            [
                'specialty' => Specialty::DERMATOLOGY,
                'phone' => '0999222333',
            ]
        );

        // Patients
        $patient = Patient::updateOrCreate(
            ['phone' => '0999333444'],
            [
                'full_name' => 'Sara Ali',
                'user_id' => $patientUser->id,
            ]
        );

        $frequentNoShow = Patient::updateOrCreate(
            ['phone' => '0999000111'],
            [
                'full_name' => 'Omar Hassan',
            ]
        );

        // Dates in Syria timezone
        $tomorrow = CarbonImmutable::now('Asia/Damascus')
            ->addDay()
            ->startOfDay();

        $nextDay = $tomorrow->addDay();

        $schedules = [
            [
                $doctor->id,
                $damascus->id,
                strtolower($tomorrow->format('l')),
            ],
            [
                $doctor->id,
                $aleppo->id,
                strtolower($nextDay->format('l')),
            ],
            [
                $doctorTwo->id,
                $latakia->id,
                strtolower($tomorrow->format('l')),
            ],
        ];

        foreach ($schedules as [$doctorId, $branchId, $day]) {
            DoctorSchedule::firstOrCreate(
                [
                    'doctor_id' => $doctorId,
                    'branch_id' => $branchId,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                ],
                [
                    'end_time' => '16:00:00',
                ]
            );
        }

        // Appointments
        $createAppointment = function (
            Patient $patient,
            CarbonImmutable $start,
            AppointmentStatus $status
        ) use ($doctor, $damascus, $admin) {
            $utc = $start->utc();

            return DB::transaction(
                fn () => Appointment::firstOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'branch_id' => $damascus->id,
                        'starts_at' => $utc,
                    ],
                    [
                        'ends_at' => $utc->addMinutes(30),
                        'status' => $status,
                        'created_by' => $admin->id,
                        'arrived_at' =>
                            $status === AppointmentStatus::ARRIVED
                                ? $utc
                                : null,
                    ]
                ),
                3
            );
        };

        // Tomorrow's appointments for reminders
        $createAppointment(
            $patient,
            $tomorrow->setTime(10, 0),
            AppointmentStatus::BOOKED
        );

        $createAppointment(
            $frequentNoShow,
            $tomorrow->setTime(11, 0),
            AppointmentStatus::BOOKED
        );

        // Two previous absences
        foreach ([8, 4] as $daysAgo) {
            $createAppointment(
                $frequentNoShow,
                $tomorrow->subDays($daysAgo)->setTime(10, 0),
                AppointmentStatus::NO_SHOW
            );
        }

        // Third missed appointment waiting to be processed
        $createAppointment(
            $frequentNoShow,
            $tomorrow->subDays(2)->setTime(10, 0),
            AppointmentStatus::BOOKED
        );

        // Arrived appointment with medical record
        $arrived = $createAppointment(
            $patient,
            $tomorrow->subDays(1)->setTime(12, 0),
            AppointmentStatus::ARRIVED
        );

        $arrived->medicalRecord()->firstOrCreate(
            [],
            [
                'diagnosis' => 'Migraine',
                'prescription' => 'Paracetamol',
                'notes' => 'Seeded test record',
            ]
        );

        // Generate API tokens for local testing
        foreach ([
            'admin' => $admin,
            'reception' => $reception,
            'doctor' => $doctorUser,
            'patient' => $patientUser,
        ] as $role => $user) {
            $name = 'seed-' . $role;

            $user->tokens()
                ->where('name', $name)
                ->delete();

            $token = $user->createToken($name)->plainTextToken;

            $this->command?->line(
                strtoupper($role) . ' TOKEN: ' . $token
            );
        }

        $this->command?->info(
            'Balsam demo data seeded successfully.'
        );
    }
}
