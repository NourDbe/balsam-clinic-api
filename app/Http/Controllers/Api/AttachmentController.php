<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Attachment;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AttachmentController extends Controller
{
    private function parentModel(
        string $type,
        int $id
    ): Appointment|MedicalRecord {
        return match ($type) {
            'appointment' => Appointment::findOrFail($id),
            'medical-record' => MedicalRecord::findOrFail($id),
            default => abort(404),
        };
    }

    private function authorizeAccess(
        Request $request,
        Appointment|MedicalRecord $parent,
        bool $write = false
    ): void {
        $user = $request->user();

        $appointment = $parent instanceof MedicalRecord
            ? $parent->appointment
            : $parent;

        abort_unless($appointment, 404);

        $allowed = match ($user->role) {
            UserRole::ADMIN => true,

            UserRole::RECEPTIONIST =>
                $parent instanceof Appointment
                && $user->branch_id !== null
                && (int) $user->branch_id === (int) $appointment->branch_id,

            UserRole::DOCTOR =>
                Doctor::query()
                    ->whereKey($appointment->doctor_id)
                    ->where('user_id', $user->id)
                    ->exists(),

            UserRole::PATIENT =>
                ! $write
                && $parent instanceof Appointment
                && Patient::query()
                    ->whereKey($appointment->patient_id)
                    ->where('user_id', $user->id)
                    ->exists(),

            default => false,
        };

        abort_unless($allowed, 403);
    }

    private function formatAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'uploaded_by' => $attachment->uploaded_by,
            'created_at' => $attachment->created_at,
        ];
    }

    public function index(
        Request $request,
        string $type,
        int $id
    ) {
        $parent = $this->parentModel($type, $id);

        $this->authorizeAccess($request, $parent);

        return response()->json([
            'data' => $parent->attachments()
                ->latest()
                ->get()
                ->map(
                    fn (Attachment $attachment) =>
                        $this->formatAttachment($attachment)
                ),
        ]);
    }

    public function store(
        Request $request,
        string $type,
        int $id
    ) {
        $parent = $this->parentModel($type, $id);

        $this->authorizeAccess($request, $parent, true);

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],
        ]);

        $file = $request->file('file');

        $path = $file->store('attachments', 'local');

        abort_unless($path, 500, 'File upload failed.');

        try {
            $attachment = $parent->attachments()->create([
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return response()->json([
            'data' => $this->formatAttachment($attachment),
        ], 201);
    }

    public function download(
        Request $request,
        Attachment $attachment
    ) {
        $parent = $attachment->attachable;

        abort_unless(
            $parent instanceof Appointment
            || $parent instanceof MedicalRecord,
            404
        );

        $this->authorizeAccess($request, $parent);

        abort_unless(
            Storage::disk('local')->exists($attachment->path),
            404
        );

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function destroy(
        Request $request,
        Attachment $attachment
    ) {
        $parent = $attachment->attachable;

        abort_unless(
            $parent instanceof Appointment
            || $parent instanceof MedicalRecord,
            404
        );

        $this->authorizeAccess($request, $parent, true);

        $attachment->delete();

        Storage::disk('local')->delete($attachment->path);

        return response()->noContent();
    }
}
