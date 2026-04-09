<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreRemittanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'treasury' => 'nullable|string|max:255',
            'receipt_number' => 'required|string|max:100|unique:remittances,receipt_number',
            'transfer_date' => 'required|date',
            'source_bank_id' => 'required|exists:bank_activities,id',
            'destination_bank_id' => 'required|exists:bank_activities,id',
            'source_bank' => 'required|string|max:255',
            'destination_bank' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'narration' => 'nullable|string|max:500',
            // 'status' => 'required|string|in:draft,pending,approved,rejected,completed',
        ];
    }
    
    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'receipt_number.unique' => 'This receipt number already exists.',
            'amount.min' => 'Amount must be greater than 0.',
            'source_bank_id.not_in' => 'Source account and destination account cannot be the same.',
            'destination_bank_id.not_in' => 'Source account and destination account cannot be the same.',
        ];
    }

    // In StoreRemittanceRequest.php
    protected function prepareForValidation()
    {
        // Format the date to MySQL format
        if ($this->transfer_date) {
            try {
                // Convert any date format to Y-m-d
                $date = Carbon::parse($this->transfer_date)->format('Y-m-d');
                $this->merge(['transfer_date' => $date]);
            } catch (\Exception $e) {
                // Leave as is, validation will catch it
            }
        }
        
        // Convert empty strings to null for bank IDs
        $this->merge([
            'source_bank_id' => $this->source_bank_id ? (int)$this->source_bank_id : null,
            'destination_bank_id' => $this->destination_bank_id ? (int)$this->destination_bank_id : null,
            'amount' => (float)$this->amount,
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if source and destination ACCOUNTS are the same (by account ID)
            if ($this->source_bank_id && $this->destination_bank_id && 
                $this->source_bank_id == $this->destination_bank_id) {
                $validator->errors()->add('destination_bank_id', 'Source account and destination account cannot be the same.');
            }
        });
    }
}