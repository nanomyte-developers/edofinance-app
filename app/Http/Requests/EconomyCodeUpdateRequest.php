<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles validation for updating an existing EconomyCode record.
 *
 * The class name has been changed from EconomyCodeStoreRequest to
 * EconomyCodeUpdateRequest to match the filename and prevent conflicts.
 */
class EconomyCodeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming authorization is handled by middleware (e.g., 'auth' or 'role')
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // We retrieve the ID of the record being updated from the route parameters.
        // It is assumed the route parameter is named 'economy_code' (e.g., /economy-codes/{economy_code}).


        // $recordId = $this->request->economy_code;
        $recordId = $this->route('economy_codess');
        // dd($this->route('economy_codess'));
        return [
            // Name: Required, max 255 chars
            'name' => ['required', 'string', 'max:255'],

            // Code: Required, max 20 chars, must be unique in the economy_codes table
            // The unique rule is modified to ignore the current record using its ID.
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('economy_codes', 'code')->ignore($recordId),
            ],

            // Status: Optional, defaults to 'active', must be one of the enum values
            'status' => ['nullable', Rule::in(['1', '0', 1,0, 'inactive', 'active'])],
        ];
    }
}
