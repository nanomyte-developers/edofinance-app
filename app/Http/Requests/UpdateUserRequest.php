<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return $this->user()->can('users.update');
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode JSON strings back to arrays for validation
        if ($this->has('roles') && is_string($this->input('roles'))) {
            $this->merge([
                'roles' => json_decode($this->input('roles'), true) ?: []
            ]);
        }

        if ($this->has('permissions') && is_string($this->input('permissions'))) {
            $this->merge([
                'permissions' => json_decode($this->input('permissions'), true) ?: []
            ]);
        }

        if ($this->has('mdas') && is_string($this->input('mdas'))) {
            $this->merge([
                'mdas' => json_decode($this->input('mdas'), true) ?: []
            ]);
        }

        // Handle can_be_signatory as boolean
        if ($this->has('can_be_signatory')) {
            $this->merge([
                'can_be_signatory' => filter_var($this->input('can_be_signatory'), FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($userId)
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'user_category_id' => 'nullable|exists:user_categories,id',
            'can_be_signatory' => 'nullable|boolean',
            'signatory_id' => 'nullable|exists:users,id',
            'signature' => 'nullable|image|mimes:jpeg,png,gif,svg|max:2048',
            'passport' => 'nullable|image|mimes:jpeg,png,gif|max:2048',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
            'mdas' => 'sometimes|array',
            'mdas.*' => 'exists:mdas,id',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'signature.image' => 'The signature must be an image file.',
            'signature.mimes' => 'The signature must be a JPEG, PNG, GIF, or SVG file.',
            'signature.max' => 'The signature must not be larger than 2MB.',
            'passport.image' => 'The passport must be an image file.',
            'passport.mimes' => 'The passport must be a JPEG, PNG, or GIF file.',
            'passport.max' => 'The passport must not be larger than 2MB.',
            'can_be_signatory.boolean' => 'The can be signatory field must be true or false.',
        ];
    }
}