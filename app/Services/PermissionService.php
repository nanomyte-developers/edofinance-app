<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionService
{
    /**
     * Get paginated permissions with filters
     */
    public function getPaginatedPermissions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Permission::query();

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';
        
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new permission
     */
    public function createPermission(array $data): Permission
    {
        return DB::transaction(function () use ($data) {
            return Permission::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);
        });
    }

    /**
     * Update permission
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        return DB::transaction(function () use ($permission, $data) {
            $permission->update([
                'name' => $data['name'],
            ]);

            return $permission->fresh();
        });
    }

    /**
     * Delete permission
     */
    public function deletePermission(Permission $permission): bool
    {
        return DB::transaction(function () use ($permission) {
            // Remove permission from roles and users
            $permission->roles()->detach();
            $permission->users()->detach();
            
            return $permission->delete();
        });
    }

    /**
     * Get all permissions for dropdown
     */
    public function getAllPermissions(): array
    {
        return Permission::orderBy('name')->get()->toArray();
    }
}