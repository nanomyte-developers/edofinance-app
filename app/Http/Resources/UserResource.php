<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    // public function toArray(Request $request): array
    // {
    //     return [
    //         'id' => $this->id,
    //         'name' => $this->name,
    //         'email' => $this->email,
    //         'email_verified_at' => $this->email_verified_at,
    //         'created_at' => $this->created_at,
    //         'updated_at' => $this->updated_at,
    //         'last_login_at' => $this->last_login_at,

    //         // User Category
    //         'user_category_id' => $this->user_category_id,
    //         'can_be_signatory' => $this->can_be_signatory,
    //         'user_category' => $this->whenLoaded('userCategory', function () {
    //             return [
    //                 'id' => $this->userCategory->id,
    //                 'name' => $this->userCategory->name,
    //                 'slug' => $this->userCategory->slug,
    //                 'requires_signature' => $this->userCategory->requires_signature,
    //                 'can_be_signatory' => $this->userCategory->can_be_signatory,
    //             ];
    //         }),
            
    //         // Signatory
    //         'signatory_id' => $this->signatory_id,
    //         'signatory' => $this->whenLoaded('signatory', function () {
    //             return [
    //                 'id' => $this->signatory->id,
    //                 'name' => $this->signatory->name,
    //                 'email' => $this->signatory->email,
    //             ];
    //         }),
            
    //         // Signature and Passport
    //         'signature' => $this->signature,
    //         'signature_url' => $this->signature_url,
    //         'passport' => $this->passport,
    //         'passport_url' => $this->passport_url,
            
    //         // Existing Roles Serialization
    //         'roles' => $this->whenLoaded('roles', function () {
    //             return $this->roles->map(function ($role) {
    //                 return [
    //                     'id' => $role->id,
    //                     'name' => $role->name,
    //                 ];
    //             })->toArray();
    //         }, []),

    //         // Existing Permissions Serialization
    //         'permissions' => $this->whenLoaded('permissions', function () {
    //             return $this->permissions->map(function ($permission) {
    //                 return [
    //                     'id' => $permission->id,
    //                     'name' => $permission->name,
    //                 ];
    //             })->toArray();
    //         }, []),

    //         // 🔥 CRITICAL FIX: Include the MDAs relationship
    //         'mdas' => $this->whenLoaded('mdas', function () {
    //             return $this->mdas->map(function ($mda) {
    //                 return [
    //                     'id' => $mda->id,
    //                     'name' => $mda->name,
    //                     // Include any required pivot data here (e.g., 'is_primary')
    //                     // 'is_primary' => $mda->pivot->is_primary, 
    //                 ];
    //             })->toArray();
    //         }, []), // Default to empty array

    //         // ... other permission fields remain here ...
    //         'role_based_permissions' => $this->whenLoaded('roles', function () {
    //             return $this->getPermissionsViaRoles()->pluck('name')->toArray();
    //         }, []),
    //         'direct_permissions' => $this->whenLoaded('permissions', function () {
    //             return $this->getDirectPermissions()->pluck('name')->toArray();
    //         }, []),
    //         'is_verified' => !is_null($this->email_verified_at),
    //         'created_at_formatted' => $this->created_at?->format('M j, Y'),
    //         'last_login_formatted' => $this->last_login_at?->format('M j, Y g:i A'),
    //     ];
    // }
    
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login_at' => $this->last_login_at,

            'user_category_id' => $this->user_category_id,
            'can_be_signatory' => $this->can_be_signatory, // ✅ This is important
            'signatory_id' => $this->signatory_id,
            
            // ✅ Passport URL
            'passport' => $this->passport,
            'passport_url' => $this->passport ? asset('storage/' . $this->passport) : null,
            
            // ✅ Signature URL
            'signature' => $this->signature,
            'signature_url' => $this->signature ? asset('storage/' . $this->signature) : null,
            
            'user_category' => $this->whenLoaded('userCategory', function () {
                return [
                    'id' => $this->userCategory->id,
                    'name' => $this->userCategory->name,
                ];
            }),
            
            'signatory' => $this->whenLoaded('signatory', function () {
                return [
                    'id' => $this->signatory->id,
                    'name' => $this->signatory->name,
                    'email' => $this->signatory->email,
                ];
            }),
            
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                })->toArray();
            }, []),

            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                })->toArray();
            }, []),

            'mdas' => $this->whenLoaded('mdas', function () {
                return $this->mdas->map(function ($mda) {
                    return [
                        'id' => $mda->id,
                        'name' => $mda->name,
                    ];
                })->toArray();
            }, []),

            'is_verified' => !is_null($this->email_verified_at),
            'created_at_formatted' => $this->created_at?->format('M j, Y'),
            'last_login_formatted' => $this->last_login_at?->format('M j, Y g:i A'),
        ];
    }

}