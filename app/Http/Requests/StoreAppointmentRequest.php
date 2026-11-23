<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return match ($user->role) {
            UserRole::ADMIN => true,

            UserRole::RECEPTIONIST =>
                $user->branch_id !== null
                && (int) $user->branch_id ===
                   (int) $this->input('branch_id'),

            UserRole::PATIENT =>
                Patient::whereKey($this->input('patient_id'))
                    ->where('user_id', $user->id)
                    ->exists(),

            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id'),
            ],
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id'),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id'),
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'المريض مطلوب.',
            'patient_id.exists' => 'المريض غير موجود.',
            'doctor_id.required' => 'الطبيب مطلوب.',
            'doctor_id.exists' => 'الطبيب غير موجود.',
            'branch_id.required' => 'الفرع مطلوب.',
            'branch_id.exists' => 'الفرع غير موجود.',
            'date.required' => 'تاريخ الموعد مطلوب.',
            'date.date_format' => 'صيغة التاريخ غير صحيحة.',
            'start_time.required' => 'وقت الموعد مطلوب.',
            'start_time.date_format' => 'صيغة الوقت غير صحيحة.',
        ];
    }
}
