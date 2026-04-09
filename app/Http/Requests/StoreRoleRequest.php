<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Temporarily disable authorization for testing
        return true;
        
        // For production: return $this->user() && $this->user()->can('roles.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'sometimes|string|exists:permissions,name',
        ];
    }

    protected function prepareForValidation()
    {
        // Ensure permissions is always an array, even if empty
        if (!$this->has('permissions') || !is_array($this->permissions)) {
            $this->merge([
                'permissions' => []
            ]);
        }

        // Filter out any empty or invalid permission values
        $filteredPermissions = array_filter($this->permissions, function($permission) {
            return !empty($permission) && is_string($permission);
        });

        $this->merge([
            'permissions' => array_values($filteredPermissions) // Reindex array
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'permissions' => 'permissions',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This role name already exists.',
            'name.required' => 'Role name is required.',
            'permissions.*.exists' => 'One or more selected permissions are invalid.',
        ];
    }
}