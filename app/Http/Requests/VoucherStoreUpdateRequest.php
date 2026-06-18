<?php
// app/Http/Requests/VoucherStoreUpdateRequest.php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class VoucherStoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $voucher = $this->route('voucher');
        $voucherId = $voucher ? $voucher->id : null;
        
        // Check if this is a final accounts request
        $isFinalAccounts = $this->input('is_final_accounts', false);
        
        \Log::info('VoucherStoreUpdateRequest rules:', [
            'has_voucher_model' => !is_null($voucher),
            'voucher_id' => $voucherId,
            'is_final_accounts' => $isFinalAccounts,
        ]);
        
        // Base rules
        $rules = [
            'year_id' => 'required|exists:financial_years,id',
            'mda_id' => 'required|exists:mdas,id',
            'voucher_date' => 'required|date',
            'voucher_number' => 'required|string|max:100|unique:vouchers,voucher_number,' . $voucherId,
            'narration' => 'required|string|max:500',
            'voucher_type' => 'required|in:standard,prepayment,Standard,Prepayment,Salary,salary,Capital,capital,Recurrent,recurrent,Gratuity,gratuity,Pension,pension',
            'status' => 'sometimes|in:Pending,Submitted,Approved,Rejected,Draft',
            'total_amount' => 'required|numeric|min:0',
            'payee_name' => 'required|string|max:255',
            // Bank activity is now REQUIRED for final accounts
            'bank_activity_id' => 'required|exists:bank_activities,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.economy_code_id' => 'nullable|exists:economy_codes,id',
            'items.*.economy_code_item_id' => 'nullable|exists:economy_code_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.sub_total' => 'required|numeric|min:0',
            'items.*.programme_code_id' => 'nullable|exists:programme_codes,id',
            'items.*.programme_code' => 'nullable|string|max:50',
            'items.*.programme_name' => 'nullable|string|max:255',
            'items.*.budget_code' => 'nullable|string|max:100',
        ];
        
        // Make schedule_id optional for final accounts
        if (!$isFinalAccounts) {
            $rules['schedule_id'] = 'sometimes|exists:schedules,id';
        } else {
            $rules['schedule_id'] = 'nullable|exists:schedules,id';
        }
        
        // Bank activity is optional for final accounts
        if (!$isFinalAccounts) {
            $rules['bank_activity_id'] = 'sometimes|exists:bank_activities,id';
        } else {
            $rules['bank_activity_id'] = 'nullable|exists:bank_activities,id';
        }
        
        // Document rules
        $rules['documents'] = 'nullable|array';
        $rules['documents.*'] = 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';
        $rules['document_types'] = 'nullable|array';
        $rules['document_types.*'] = 'nullable|array';
        $rules['document_types.*.type'] = 'nullable|string';
        $rules['document_types.*.label'] = 'nullable|string|max:255';
        $rules['document_types.*.file_name'] = 'nullable|string|max:255';
        $rules['documents_to_delete'] = 'nullable|array';
        $rules['documents_to_delete.*'] = 'nullable|integer';
        
        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.*.description.required' => 'Each line item must have a description.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            'voucher_number.unique' => 'This voucher number already exists for another voucher.',
            'total_amount.required' => 'Total amount is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $data = $this->all();
        
        // Check if this is a final accounts request
        $isFinalAccounts = $this->input('is_final_accounts', false);
        
        // Calculate total_amount if not set
        if (!isset($data['total_amount']) && isset($data['items'])) {
            if (is_string($data['items'])) {
                $items = json_decode($data['items'], true);
            } else {
                $items = $data['items'];
            }
            
            if (is_array($items)) {
                $total = collect($items)->sum(function ($item) {
                    return isset($item['sub_total']) ? (float) $item['sub_total'] : 0;
                });
                $this->merge(['total_amount' => round($total, 2)]);
            }
        }
        
        // If items is a string (JSON), decode it
        if (isset($data['items']) && is_string($data['items'])) {
            $data['items'] = json_decode($data['items'], true);
            $this->merge(['items' => $data['items']]);
        }
        
        // Set default values for final accounts
        if ($isFinalAccounts) {
            // Set default schedule_id to null if not provided
            if (!isset($data['schedule_id']) || empty($data['schedule_id'])) {
                $this->merge(['schedule_id' => null]);
            }
            
            // Set default bank_activity_id to null if not provided
            if (!isset($data['bank_activity_id']) || empty($data['bank_activity_id'])) {
                $this->merge(['bank_activity_id' => null]);
            }
        }
        
        // Ensure each item has programme code fields
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (!isset($item['programme_code_id'])) {
                    $item['programme_code_id'] = null;
                }
                if (!isset($item['programme_code'])) {
                    $item['programme_code'] = null;
                }
                if (!isset($item['programme_name'])) {
                    $item['programme_name'] = null;
                }
                if (!isset($item['budget_code'])) {
                    $item['budget_code'] = null;
                }
            }
            $this->merge(['items' => $data['items']]);
        }
        
        // If document_types is a string (JSON), decode it
        if (isset($data['document_types']) && is_string($data['document_types'])) {
            $data['document_types'] = json_decode($data['document_types'], true);
            $this->merge(['document_types' => $data['document_types']]);
        }
        
        // If documents_to_delete is a string, convert it
        if (isset($data['documents_to_delete']) && is_string($data['documents_to_delete'])) {
            if (str_contains($data['documents_to_delete'], '[')) {
                $data['documents_to_delete'] = json_decode($data['documents_to_delete'], true);
            } elseif (str_contains($data['documents_to_delete'], ',')) {
                $data['documents_to_delete'] = explode(',', $data['documents_to_delete']);
            } else {
                $data['documents_to_delete'] = [$data['documents_to_delete']];
            }
            $this->merge(['documents_to_delete' => $data['documents_to_delete']]);
        }

        // Ensure status is set properly
        if (!isset($data['status'])) {
            // For final accounts, default to Approved
            if ($isFinalAccounts) {
                $this->merge(['status' => 'Approved']);
            } else {
                $this->merge(['status' => 'Draft']);
            }
        }
        
        \Log::info('Prepared validation data:', [
            'has_total_amount' => isset($this->total_amount),
            'total_amount_value' => $this->total_amount ?? 'NOT SET',
            'items_count' => is_array($this->items) ? count($this->items) : 0,
            'is_final_accounts' => $isFinalAccounts,
            'schedule_id' => $this->schedule_id ?? 'NOT SET',
        ]);
    }
}