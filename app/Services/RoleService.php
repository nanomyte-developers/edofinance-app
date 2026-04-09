<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    /**
     * Get paginated roles with filters
     */
    public function getPaginatedRoles(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::with(['permissions']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';
        
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new role with permissions
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'guard_name' => 'web',
            ]);

            // Sync permissions if provided
            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Update role with permissions
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            // Sync permissions if provided - ensure it's an array
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                // Filter out any empty or null values
                $validPermissions = array_filter($data['permissions'], function($permission) {
                    return !empty($permission) && is_string($permission);
                });
                
                $role->syncPermissions($validPermissions);
            } else {
                // If no permissions provided, remove all permissions
                $role->syncPermissions([]);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Delete role
     */
    public function deleteRole(Role $role): bool
    {
        return DB::transaction(function () use ($role) {
            // Prevent deleting admin role
            if ($role->name === 'admin') {
                throw new \Exception('Cannot delete admin role.');
            }

            // Remove role from users and permissions
            $role->users()->detach();
            $role->permissions()->detach();
            
            return $role->delete();
        });
    }

    /**
     * Get all permissions for dropdown
     */
    public function getAllPermissions(): array
    {
        return Permission::orderBy('name')->get()->toArray();
    }

    /**
     * Get role with permissions
     */
    public function getRoleWithPermissions(int $roleId): Role
    {
        return Role::with(['permissions'])->findOrFail($roleId);
    }
}