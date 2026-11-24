<?php

namespace App\Models\Scopes;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ReceptionBranchScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {
        $user = Auth::guard()->user();

        if (! $user || $user->role !== UserRole::RECEPTIONIST) {
            return;
        }

        if ($user->branch_id === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where(
            $model->qualifyColumn('branch_id'),
            $user->branch_id
        );
    }
}
