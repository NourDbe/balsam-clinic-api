<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Models\Scopes\ReceptionBranchScope;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'created_by',
        'starts_at',
        'ends_at',
        'status',
        'arrived_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'arrived_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }
    public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return match ($user->role) {
        UserRole::ADMIN => $query,

        UserRole::RECEPTIONIST => $query
            ->where('branch_id', $user->branch_id ?? -1),

        UserRole::DOCTOR => $query
            ->whereHas('doctor', fn (Builder $q) =>
                $q->where('user_id', $user->id)
            ),

        UserRole::PATIENT => $query
            ->whereHas('patient', fn (Builder $q) =>
                $q->where('user_id', $user->id)
            ),

        default => $query->whereRaw('1 = 0'),
    };
}

public function scopeFilter(Builder $query, array $filters): Builder
{
    if (!empty($filters['branch_id'])) {
        $query->where('branch_id', $filters['branch_id']);
    }

    if (!empty($filters['doctor_id'])) {
        $query->where('doctor_id', $filters['doctor_id']);
    }

    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (!empty($filters['specialty'])) {
        $query->whereHas('doctor', fn (Builder $q) =>
            $q->where('specialty', $filters['specialty'])
        );
    }

    if (!empty($filters['date'])) {
        $start = Carbon::parse(
            $filters['date'], 'Asia/Damascus'
        )->startOfDay()->utc();

        $end = Carbon::parse(
            $filters['date'], 'Asia/Damascus'
        )->endOfDay()->utc();

        $query->whereBetween('starts_at', [
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ]);
    }

    return $query;
}
protected static function booted(): void
{
    static::addGlobalScope(
        new ReceptionBranchScope()
    );
}
public function attachments(): MorphMany
{
    return $this->morphMany(
        Attachment::class,
        'attachable'
    );
}
}
