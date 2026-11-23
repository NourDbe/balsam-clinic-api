<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'city',
        'address',
    ];
    public function doctorSchedules(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(DoctorSchedule::class);
}
public function appointments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Appointment::class);
}
}
