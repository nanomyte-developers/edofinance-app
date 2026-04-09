<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Remittance;

class UpdateRemittanceRequest extends FormRequest
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
        $remittance = $this->route('remittance');
        
        return [
            'receipt_number' => [
                'required',
                'string',
                'max:100',
                'unique:remittances,receipt_number,' . $remittance->id
            ],
            'transfer_date' => 'required|date',
            'source_bank_id' => 'required|exists:bank_activities,id',
            'destination_bank_id' => [
                'required',
                'exists:bank_activities,id',
                function ($attribute, $value, $fail) use ($remittance) {
                    // For UPDATE: Check if trying to change to same account
                    if ($value == $this->source_bank_id) {
                        // Only fail if it's changing FROM different accounts TO same accounts
                        if ($remittance->source_bank_id != $remittance->destination_bank_id) {
                            $fail('Source account and destination account cannot be the same.');
                        }
                    }
                }
            ],
            'source_bank' => 'required|string|max:255',
            'destination_bank' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'narration' => 'nullable|string|max:500',
            'status' => 'required|string|in:Draft,Saved,Submitted,Pending Approval,Approved,Rejected,Returned,Sent Back,Declined', // Make sure this is included
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
        ];
    }

    protected function prepareForValidation()
    {
        // Format the date to MySQL format
        if ($this->transfer_date) {
            try {
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
}