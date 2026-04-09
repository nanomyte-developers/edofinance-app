<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Temporarily disable authorization for testing
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId)
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'sometimes|string|exists:permissions,name',
        ];
    }

    /**
     * Prepare the data for validation.
     */
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
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'permissions.*.exists' => 'One or more selected permissions are invalid.',
        ];
    }
}