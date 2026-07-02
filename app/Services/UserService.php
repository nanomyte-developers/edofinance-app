<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 100): LengthAwarePaginator
    {
        // $query = User::with(['roles', 'permissions']) // This will work once HasRoles trait is added
        //     ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at', 'last_login_at']);
        $query = User::with(['roles', 'permissions', 'userCategory', 'signatory', 'mdas'])
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at', 'last_login_at', 'user_category_id', 'signatory_id', 'signature', 'passport']);

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

            // Handle file uploads
            $signaturePath = null;
            $passportPath = null;
            
            if (isset($data['signature']) && $data['signature'] instanceof \Illuminate\Http\UploadedFile) {
                $signaturePath = $data['signature']->store('signatures', 'public');
            }
            
            if (isset($data['passport']) && $data['passport'] instanceof \Illuminate\Http\UploadedFile) {
                $passportPath = $data['passport']->store('passports', 'public');
            }
            
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => isset($data['email_verified']) && $data['email_verified'] ? now() : null,
                'user_category_id' => $data['user_category_id'] ?? null,
                'can_be_signatory' => $data['can_be_signatory'] ?? false,
                'signatory_id' => $data['signatory_id'] ?? null,
                'signature' => $signaturePath,
                'passport' => $passportPath,
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

            // ✅ Assign MDAs with pivot data
            if (!empty($data['mdas'])) {
                $mdaData = [];
                $assignedById = Auth::id();
                
                foreach ($data['mdas'] as $mdaId) {
                    $mdaData[$mdaId] = [
                        'assigned_by_id' => $assignedById,
                        'status' => 1,
                        'is_primary' => false,
                        'effective_date' => now(),
                    ];
                }
                
                Log::info('Service: Syncing MDAs with pivot data: ', [
                    'mdas' => $data['mdas'],
                    'pivot_data' => $mdaData
                ]);
                
                $user->mdas()->sync($mdaData);
            }

            return $user->load(['roles', 'permissions', 'mdas', 'userCategory', 'signatory']);
        });
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            Log::info('UserService - Updating user:', [
                'user_id' => $user->id,
                'data_keys' => array_keys($data),
                'has_signature_file' => isset($data['signature']) && $data['signature'] instanceof \Illuminate\Http\UploadedFile,
                'has_passport_file' => isset($data['passport']) && $data['passport'] instanceof \Illuminate\Http\UploadedFile,
            ]);

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

            // Update user category
            if (isset($data['user_category_id'])) {
                $updateData['user_category_id'] = $data['user_category_id'] === '' || $data['user_category_id'] === null 
                    ? null 
                    : $data['user_category_id'];
            }

            // Update signatory
            if (isset($data['signatory_id'])) {
                $updateData['signatory_id'] = $data['signatory_id'] === '' || $data['signatory_id'] === null 
                    ? null 
                    : $data['signatory_id'];
            }

            // Update can_be_signatory
            if (isset($data['can_be_signatory'])) {
                $updateData['can_be_signatory'] = filter_var($data['can_be_signatory'], FILTER_VALIDATE_BOOLEAN);
            }

            // Handle signature upload
            if (isset($data['signature']) && $data['signature'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old signature if exists
                if ($user->signature) {
                    Storage::disk('public')->delete($user->signature);
                    Log::info('Deleted old signature:', ['path' => $user->signature]);
                }
                $updateData['signature'] = $data['signature']->store('signatures', 'public');
                Log::info('Uploaded new signature:', ['path' => $updateData['signature']]);
            }

            // Handle passport upload
            if (isset($data['passport']) && $data['passport'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old passport if exists
                if ($user->passport) {
                    Storage::disk('public')->delete($user->passport);
                    Log::info('Deleted old passport:', ['path' => $user->passport]);
                }
                $updateData['passport'] = $data['passport']->store('passports', 'public');
                Log::info('Uploaded new passport:', ['path' => $updateData['passport']]);
            }

            Log::info('UserService - Update data:', $updateData);

            $user->update($updateData);

            // Sync roles if provided
            if (isset($data['roles'])) {
                Log::info('UserService - Syncing roles:', $data['roles']);
                $user->syncRoles($data['roles']);
            }

            // Sync permissions if provided
            if (isset($data['permissions'])) {
                Log::info('UserService - Syncing permissions:', $data['permissions']);
                $user->syncPermissions($data['permissions']);
            }

            // ✅ Sync MDAs with pivot data
            if (isset($data['mdas'])) {
                $mdaData = [];
                $assignedById = Auth::id();
                
                foreach ($data['mdas'] as $mdaId) {
                    $mdaData[$mdaId] = [
                        'assigned_by_id' => $assignedById,
                        'status' => 1,
                        'is_primary' => false,
                        'effective_date' => now(),
                    ];
                }
                
                Log::info('UserService - Syncing MDAs with pivot data:', [
                    'user_id' => $user->id,
                    'mdas_to_sync' => $data['mdas'],
                    'pivot_data' => $mdaData
                ]);
                
                $user->mdas()->sync($mdaData);
            }


            // Refresh the model with relationships
            $user->refresh();
            $user->load(['roles', 'permissions', 'mdas', 'userCategory', 'signatory']);

            Log::info('UserService - User updated successfully:', [
                'user_id' => $user->id,
                'signature_path' => $user->signature,
                'passport_path' => $user->passport,
            ]);

            return $user;
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
            // Delete signature file if exists
            if ($user->signature) {
                Storage::disk('public')->delete($user->signature);
            }
            
            // Delete passport file if exists
            if ($user->passport) {
                Storage::disk('public')->delete($user->passport);
            }
            
            // Remove roles and permissions
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->mdas()->detach();
            
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
        return User::with(['roles', 'permissions', 'userCategory', 'signatory', 'mdas'])
            ->findOrFail($userId);
    }
}