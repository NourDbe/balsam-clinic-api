<?php

namespace App\Http\Controllers\Api;

use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Services\DoctorDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    public function index(
        Request $request,
        DoctorDirectoryService $service
    ): AnonymousResourceCollection {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'specialty' => ['sometimes', Rule::enum(Specialty::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();

        if (
            $user->role === UserRole::RECEPTIONIST &&
            (int) $user->branch_id !== (int) $data['branch_id']
        ) {
            abort(403, 'Access denied for this branch.');
        }

        $specialty = isset($data['specialty'])
            ? Specialty::from($data['specialty'])
            : null;

        $doctors = $service->list(
            (int) $data['branch_id'],
            $specialty
        );

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = (int) ($data['per_page'] ?? 15);

        $paginator = new LengthAwarePaginator(
            array_slice($doctors, ($page - 1) * $perPage, $perPage),
            count($doctors),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return DoctorResource::collection($paginator);
    }
}
