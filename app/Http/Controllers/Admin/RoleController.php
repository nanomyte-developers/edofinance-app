<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\RoleService;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search', ''),
            'sort' => $request->get('sort', 'name'),
            'order' => $request->get('order', 'asc'),
        ];

        $roles = $this->roleService->getPaginatedRoles($filters);

        return Inertia::render('admin/roles/index', [
            'roles' => [
                'data' => RoleResource::collection($roles->items())->toArray($request),
                'total' => $roles->total(),
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'links' => $roles->linkCollection()->toArray(),
            ],
            'filters' => $filters,
            'all_permissions' => $this->roleService->getAllPermissions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $role = $this->roleService->createRole($request->validated());

            return redirect()
                ->route('roles.index')
                ->with('success', "Role '{$role->name}' created successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            $role = $this->roleService->updateRole($role, $request->validated());

            return redirect()
                ->route('roles.index')
                ->with('success', "Role '{$role->name}' updated successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        try {
            $roleName = $role->name;
            $this->roleService->deleteRole($role);

            return redirect()
                ->route('roles.index')
                ->with('success', "Role '{$roleName}' deleted successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to delete role: ' . $e->getMessage());
        }
    }
}