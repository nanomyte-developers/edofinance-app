<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreJournalRequest extends FormRequest
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
    

    public function rules()
    {

    
        return [
            // 'journal_number' => 'required|string|unique:journals',
            'journal_date' => 'required|date',
            'posting_date' => 'required|date|after_or_equal:journal_date',
            'description' => 'required|string|max:500',
            'mda_id' => 'required|exists:mdas,id',
           // 'economic_code_id' => 'required|exists:economy_codes,id',
            'administrative_code_id' => 'required|exists:administrative_sector_codes,id',
            'administrative_sector_code_id' => 'required|exists:administrative_sector_codes,id',
            'status' => 'required|in:draft,pending,approved',
            'remarks' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'batch_number' => 'nullable|string',
            'financial_year' => 'nullable|integer',
            'journal_type' => 'required|string',

            // Journal entries validation
            'entries' => 'required|array|min:2',
            'entries.*.economic_code_id' => 'required|exists:economy_codes,id',
            'entries.*.account_code' => 'required|string',
            'entries.*.description' => 'nullable|string|max:500',
            'entries.*.debit_amount' => 'required_without:entries.*.credit_amount|numeric|min:0',
            'entries.*.credit_amount' => 'required_without:entries.*.debit_amount|numeric|min:0',
            'entries.*.tax_amount' => 'nullable|numeric|min:0',
            'entries.*.tax_code' => 'nullable|string',
            'entries.*.cost_center' => 'nullable|string',
            'entries.*.project_code' => 'nullable|string',
            'entries.*.reference' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'entries.*.economic_code_id.required' => 'Each entry must have an economic code',
            'entries.*.account_code.required' => 'Each entry must have an account code',
            'entries.*.debit_amount.required_without' => 'Each entry must have either debit or credit amount',
            'entries.*.credit_amount.required_without' => 'Each entry must have either debit or credit amount',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Format dates to MySQL format
        if ($this->journal_date) {
            try {
                $date = Carbon::parse($this->journal_date)->format('Y-m-d');
                $this->merge(['journal_date' => $date]);
            } catch (\Exception $e) {
                // Leave as is, validation will catch it
            }
        }

        if ($this->posting_date) {
            try {
                $date = Carbon::parse($this->posting_date)->format('Y-m-d');
                $this->merge(['posting_date' => $date]);
            } catch (\Exception $e) {
                // Leave as is, validation will catch it
            }
        }

        if ($this->next_recurring_date) {
            try {
                $date = Carbon::parse($this->next_recurring_date)->format('Y-m-d');
                $this->merge(['next_recurring_date' => $date]);
            } catch (\Exception $e) {
                // Leave as is, validation will catch it
            }
        }

        // Convert main form fields to proper types
        if ($this->mda_id) {
            $this->merge(['mda_id' => (int) $this->mda_id]);
        }

        if ($this->economic_code_id) {
            $this->merge(['economic_code_id' => (int) $this->economic_code_id]);
        }

        if ($this->administrative_code_id) {
            $this->merge(['administrative_code_id' => (int) $this->administrative_code_id]);
        }

        if ($this->administrative_sector_code_id) {
            $this->merge(['administrative_sector_code_id' => (int) $this->administrative_sector_code_id]);
        }

        if ($this->financial_year) {
            $this->merge(['financial_year' => (int) $this->financial_year]);
        }

        // Convert entries data
        if ($this->entries && is_array($this->entries)) {
            $entries = [];
            foreach ($this->entries as $index => $entry) {
                $entries[] = [
                    'economic_code_id' => isset($entry['economic_code_id']) ? (int) $entry['economic_code_id'] : null,
                    'account_code' => $entry['account_code'] ?? null,
                    'description' => $entry['description'] ?? null,
                    'debit_amount' => isset($entry['debit_amount']) ? (float) $entry['debit_amount'] : 0,
                    'credit_amount' => isset($entry['credit_amount']) ? (float) $entry['credit_amount'] : 0,
                    'cost_center' => $entry['cost_center'] ?? null,
                    'project_code' => $entry['project_code'] ?? null,
                    'reference' => $entry['reference'] ?? null,
                    'tax_code' => $entry['tax_code'] ?? null,
                    'tax_amount' => isset($entry['tax_amount']) ? (float) $entry['tax_amount'] : 0,
                ];
            }
            $this->merge(['entries' => $entries]);
        }

        // Set default status if not provided
        if (! $this->has('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Set default financial year if not provided
        if (! $this->has('financial_year')) {
            $this->merge(['financial_year' => Carbon::now()->year]);
        }

        // Convert boolean values
        $this->merge([
            'is_recurring' => $this->boolean('is_recurring'),
            'department_id' => $this->department_id ? (int) $this->department_id : null,
            'gl_category_id' => $this->gl_category_id ? (int) $this->gl_category_id : null,
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate journal is balanced
            if ($this->entries && is_array($this->entries)) {
                $totalDebit = 0;
                $totalCredit = 0;

                foreach ($this->entries as $entry) {
                    $totalDebit += $entry['debit_amount'] ?? 0;
                    $totalCredit += $entry['credit_amount'] ?? 0;
                }

                if (abs($totalDebit - $totalCredit) > 0.01) {
                    $validator->errors()->add('entries', 'Journal is not balanced. Total debit must equal total credit.');
                }

                // Validate that each entry has only debit or credit, not both
                foreach ($this->entries as $index => $entry) {
                    $debit = $entry['debit_amount'] ?? 0;
                    $credit = $entry['credit_amount'] ?? 0;

                    if ($debit > 0 && $credit > 0) {
                        $validator->errors()->add("entries.{$index}", 'Entry cannot have both debit and credit amounts.');
                    }

                    if ($debit == 0 && $credit == 0) {
                        $validator->errors()->add("entries.{$index}", 'Entry must have either debit or credit amount.');
                    }
                }
            }

            // Validate recurring journal has frequency if is_recurring is true
            if ($this->is_recurring && ! $this->recurring_frequency) {
                $validator->errors()->add('recurring_frequency', 'Recurring frequency is required for recurring journals.');
            }

            if ($this->is_recurring && $this->recurring_frequency && ! $this->next_recurring_date) {
                $validator->errors()->add('next_recurring_date', 'Next recurring date is required for recurring journals.');
            }
        });
    }
}
