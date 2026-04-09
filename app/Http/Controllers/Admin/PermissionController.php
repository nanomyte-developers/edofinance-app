<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\PermissionService;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;

class PermissionController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
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

        $permissions = $this->permissionService->getPaginatedPermissions($filters);

        return Inertia::render('admin/permissions/index', [
            'permissions' => [
                'data' => PermissionResource::collection($permissions->items())->toArray($request),
                'total' => $permissions->total(),
                'current_page' => $permissions->currentPage(),
                'per_page' => $permissions->perPage(),
                'links' => $permissions->linkCollection()->toArray(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePermissionRequest $request)
    {
        try {
            $permission = $this->permissionService->createPermission($request->validated());

            return redirect()
                ->route('permissions.index')
                ->with('success', "Permission '{$permission->name}' created successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        try {
            $permission = $this->permissionService->updatePermission($permission, $request->validated());

            return redirect()
                ->route('permissions.index')
                ->with('success', "Permission '{$permission->name}' updated successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to update permission: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        try {
            $permissionName = $permission->name;
            $this->permissionService->deletePermission($permission);

            return redirect()
                ->route('permissions.index')
                ->with('success', "Permission '{$permissionName}' deleted successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to delete permission: ' . $e->getMessage());
        }
    }
}