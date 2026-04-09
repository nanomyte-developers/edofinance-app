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
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // $userId = $this->route('user');

        // return [
        //     'name' => 'required|string|max:255',
        //     'email' => 'required|email|unique:users,email,' . $userId,
        //     'password' => ['nullable', 'confirmed', Password::defaults()],
        //     'email_verified' => 'boolean',
        //     'roles' => 'sometimes|array',
        //     'roles.*' => 'string|exists:roles,name',
        //     'permissions' => 'sometimes|array',
        //     'permissions.*' => 'string|exists:permissions,name',
        //     'mdas' => 'sometimes|array',
        //     'mdas.*' => 'exists:mdas,id',
        // ];
        $userId = $this->route('user')->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($userId)
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
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
}