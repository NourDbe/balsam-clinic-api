<?php

namespace App\Http\Requests;

use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('medicalRecord');

        return $record instanceof MedicalRecord
            && Gate::allows('update', $record);
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['sometimes', 'required', 'string', 'max:10000'],
            'prescription' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'التشخيص لا يمكن أن يكون فارغاً.',
            'diagnosis.string' => 'التشخيص يجب أن يكون نصاً.',
            'diagnosis.max' => 'التشخيص طويل جداً.',
            'prescription.string' => 'الوصفة يجب أن تكون نصاً.',
            'prescription.max' => 'الوصفة طويلة جداً.',
            'notes.string' => 'الملاحظات يجب أن تكون نصاً.',
            'notes.max' => 'الملاحظات طويلة جداً.',
        ];
    }
}
