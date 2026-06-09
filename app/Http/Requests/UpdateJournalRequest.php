<?php

namespace App\Http\Requests;

use App\Models\Journal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdateJournalRequest extends FormRequest
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
    // public function rules(): array
    // {
    //     $journal = $this->route('journal');

    //     return [
    //         'journal_number' => [
    //             'nullable',
    //             'string',
    //             'max:50',
    //             Rule::unique('journals', 'journal_number')->ignore($journal->id),
    //         ],
    //         'journal_date' => 'required|date',
    //         'posting_date' => 'required|date|after_or_equal:journal_date',
    //         'description' => 'required|string|max:500',
    //         'remarks' => 'nullable|string|max:1000',
    //         'reference_number' => 'nullable|string|max:100',
    //         'source_document' => 'nullable|string|max:255',
    //         'batch_number' => 'nullable|string|max:50',
    //         'financial_year' => 'nullable|integer|min:2000|max:'.(date('Y') + 1),
    //         'department_id' => 'nullable|exists:departments,id',
    //         'gl_category_id' => 'nullable|exists:gl_categories,id',
    //         'status' => [
    //             'required',
    //             'string',
    //             Rule::in(['draft', 'saved', 'pending', 'approved', 'rejected', 'returned', 'sent back', 'declined', 'cancelled']),
    //         ],
    //         'is_recurring' => 'boolean',
    //         'recurring_frequency' => 'nullable|string|in:daily,weekly,monthly,quarterly,yearly',
    //         'next_recurring_date' => 'nullable|date|after:journal_date',

    //         // Journal entries validation
    //         'entries' => 'required|array|min:2',
    //         'entries.*.account_code' => 'required|string|max:50|exists:gl_accounts,account_code',
    //         'entries.*.description' => 'nullable|string|max:500',
    //         'entries.*.debit_amount' => 'required_without:entries.*.credit_amount|numeric|min:0',
    //         'entries.*.credit_amount' => 'required_without:entries.*.debit_amount|numeric|min:0',
    //         'entries.*.cost_center' => 'nullable|string|max:50',
    //         'entries.*.project_code' => 'nullable|string|max:50',
    //         'entries.*.reference' => 'nullable|string|max:100',
    //         'entries.*.tax_code' => 'nullable|string|max:50',
    //         'entries.*.tax_amount' => 'nullable|numeric|min:0',
    //     ];
    // }
    // In StoreJournalRequest.php
    public function rules()
    {

        $recordId = $this->route('journal');
        // dd($recordId);

        // dd($this->request->all());
        return [

            'journal_number' => 'required|string|max:50|unique:journals,journal_number,' . $recordId->id,
            'journal_date' => 'required|date',
            'posting_date' => 'required|date|after_or_equal:journal_date',
            'description' => 'required|string|max:500',
            'mda_id' => 'required|exists:mdas,id',
            'journal_type' => 'required|string',
            // 'economic_code_id' => 'required|exists:economy_codes,id',
            'administrative_code_id' => 'required|exists:administrative_sector_codes,id',
            'administrative_sector_code_id' => 'required|exists:administrative_sector_codes,administrative_code_id',
            'reference_number' => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'financial_year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'remarks' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,pending,approved',

            'entries' => 'required|array|min:2',
            // 'entries.*.economic_code_id' => 'required|exists:economy_codes,id',
            'entries.*.account_code' => [
                'required',
                'string',
                'max:50',
                // Custom validation for economic code items
                // function ($attribute, $value, $fail) {
                //     // Get the index from the attribute name
                //     $index = explode('.', $attribute)[1];
                //     $economicCodeId = request()->input("entries.{$index}.economic_code_id");

                //     if (! $economicCodeId) {
                //         $fail('Economic code is required.');

                //         return;
                //     }

                //     // Check if the account code exists in economy_code_items
                //     $exists = \App\Models\EconomyCodeItem::where('code', $value)
                //         ->where('economy_code_id', $economicCodeId)
                //         ->where('status', 'active')
                //         ->exists();

                //     if (! $exists) {
                //         $fail('The selected account code does not exist for the chosen economic code.');
                //     }
                // },
            ],
            'entries.*.description' => 'nullable|string|max:500',
            'entries.*.debit_amount' => 'nullable|numeric|min:0',
            'entries.*.credit_amount' => 'nullable|numeric|min:0',
            'entries.*.cost_center' => 'nullable|string|max:50',
            'entries.*.project_code' => 'nullable|string|max:50',
            'entries.*.reference' => 'nullable|string|max:100',
            'entries.*.tax_code' => 'nullable|string|max:50',
            'entries.*.tax_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'journal_number.unique' => 'This journal number already exists.',
            'entries.min' => 'Journal must have at least two entries.',
            'entries.*.account_code.exists' => 'The selected account code does not exist.',
            'entries.*.debit_amount.required_without' => 'Each entry must have either debit or credit amount.',
            'entries.*.credit_amount.required_without' => 'Each entry must have either debit or credit amount.',
            'posting_date.after_or_equal' => 'Posting date must be on or after journal date.',
        ];
    }

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

        // Convert entries data
        if ($this->entries && is_array($this->entries)) {
            $entries = [];
            foreach ($this->entries as $index => $entry) {
                $entries[] = [
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
        $journal = $this->route('journal');

        $validator->after(function ($validator) use ($journal) {
            // Check if journal can be edited
            if (! $journal->canEdit() && !Auth::user()->hasRole('admin')) {
                $validator->errors()->add('status', 'This journal cannot be edited because of its current status.');
            }

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

            // Validate status transition
            $oldStatus = strtolower($journal->status);
            $newStatus = strtolower($this->status);

            // If trying to change from approved to something else
            // if ($oldStatus === 'approved' && $newStatus !== 'approved') {
            //     $validator->errors()->add('status', 'Cannot change status from approved. Please create a reversal journal instead.');
            // }

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
