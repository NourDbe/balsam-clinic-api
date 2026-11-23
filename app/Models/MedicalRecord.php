<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    protected $fillable = [
        'appointment_id',
        'diagnosis',
        'prescription',
        'notes',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(AuditLog::class, 'auditable');
}
}
