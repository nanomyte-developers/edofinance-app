<?php

namespace App\Services;

use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 100): LengthAwarePaginator
    {
        $query = User::with(['roles', 'permissions']) // This will work once HasRoles trait is added
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at', 'last_login_at']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($filters['status'] === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';
        
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => isset($data['email_verified']) && $data['email_verified'] ? now() : null,
            ]);

            logger('Service: User created with ID: ' . $user->id);

            // Assign roles if provided
            if (!empty($data['roles'])) {
                logger('Service: Syncing roles: ', $data['roles']);
                $user->syncRoles($data['roles']);
            }

            // Assign permissions if provided
            if (!empty($data['permissions'])) {
                logger('Service: Syncing permissions: ', $data['permissions']);
                $user->syncPermissions($data['permissions']);
            }

            // Assign MDAs if provided
            if (!empty($data['mdas'])) {
                logger('Service: Syncing MDAs: ', $data['mdas']);
                $user->mdas()->sync($data['mdas']);
            } else {
                logger('Service: No MDAs to sync');
            }

            return $user->load(['roles', 'permissions', 'mdas']);
        });
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            // Update password if provided
            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // Update email verification status
            if (isset($data['email_verified'])) {
                $updateData['email_verified_at'] = $data['email_verified'] ? now() : null;
            }

            $user->update($updateData);

            // Sync roles if provided
            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            // Sync permissions if provided
            if (isset($data['permissions'])) {
                $user->syncPermissions($data['permissions']);
            }

            // Sync MDAs if provided
            if (isset($data['mdas'])) {
                $user->mdas()->sync($data['mdas']);
            }

            return $user->load(['roles', 'permissions', 'mdas']);
        });
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): bool
    {
        // Prevent deleting super admin (user with ID 1)
        if ($user->id === 1) {
            throw new \Exception('Cannot delete super admin user.');
        }

        return DB::transaction(function () use ($user) {
            // Remove roles and permissions
            $user->roles()->detach();
            $user->permissions()->detach();
            
            return $user->delete();
        });
    }

    /**
     * Sync user roles
     */
    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);
        return $user->load('roles');
    }

    /**
     * Sync user permissions
     */
    public function syncPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($permissions);
        return $user->load('permissions');
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        return Role::all()->pluck('name')->toArray();
    }

    /**
     * Update user last login timestamp
     */
    public function updateLastLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
    }

    /**
     * Get user with roles and permissions
     */
    public function getUserWithPermissions(int $userId): User
    {
        return User::with(['roles', 'permissions'])
            ->findOrFail($userId);
    }
}