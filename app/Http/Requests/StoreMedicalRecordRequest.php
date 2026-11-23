<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && Gate::allows(
                'create',
                [MedicalRecord::class, $appointment]
            );
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['required', 'string', 'max:10000'],
            'prescription' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'التشخيص مطلوب.',
            'diagnosis.string' => 'التشخيص يجب أن يكون نصاً.',
            'diagnosis.max' => 'التشخيص طويل جداً.',
            'prescription.string' => 'الوصفة يجب أن تكون نصاً.',
            'prescription.max' => 'الوصفة طويلة جداً.',
            'notes.string' => 'الملاحظات يجب أن تكون نصاً.',
            'notes.max' => 'الملاحظات طويلة جداً.',
        ];
    }
}
