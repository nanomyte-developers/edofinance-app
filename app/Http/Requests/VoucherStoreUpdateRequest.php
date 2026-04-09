<?php

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
        // $voucherId = $this->route('voucher') ? $this->route('voucher')->id : null;

        // dd($this);
        $voucher = $this->route('voucher');
    
        $voucherId = $voucher ? $voucher->id : null;

        \Log::info('VoucherStoreUpdateRequest rules:', [
            'has_voucher_model' => !is_null($voucher),
            'voucher_id' => $voucherId,
            'voucher_type' => gettype($voucher),
        ]);
        
        return [
            'year_id' => 'required|exists:financial_years,id',
            'mda_id' => 'required|exists:mdas,id',
            'voucher_date' => 'required|date',
            'voucher_number' => 'required|string|max:100|unique:vouchers,voucher_number,' . $voucherId,
            'narration' => 'required|string|max:500',
            'voucher_type' => 'required|in:standard,prepayment,Standard,Prepayment,Salary,salary',
            'status' => 'sometimes|in:Pending,Submitted,Approved,Rejected,Draft',
            'schedule_id' => 'sometimes|exists:schedules,id',
            'total_amount' => 'required|numeric|min:0', // Make sure this is required
            'payee_name' => 'required|string|max:255',
            'bank_activity_id' => 'sometimes|exists:bank_activities,id',

            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.economy_code_id' => 'nullable|exists:economy_codes,id',
            'items.*.economy_code_item_id' => 'nullable|exists:economy_code_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.sub_total' => 'required|numeric|min:0',

            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',

            // Make document_types validation more flexible
            'document_types' => 'nullable|array',
            'document_types.*' => 'nullable|array',
            'document_types.*.type' => 'nullable|string',
            'document_types.*.label' => 'nullable|string|max:255',
            'document_types.*.file_name' => 'nullable|string|max:255',
            
            'documents_to_delete' => 'nullable|array',
            'documents_to_delete.*' => 'nullable|integer',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_types.*.type.in' => 'The document type must be one of: Approval Memo, Release Warrant, Exco Approval/Conclusion, Ministerial Tender Board, State Tenders Board, Certificate Of Incorporation, Tax Clearance, Tax Identification Number (TIN), Procurement Registration Certificate, Advance Payment Guarantee (APG), Invoice, Receipt, Delivery Note) or Other.',
            'items.*.description.required' => 'Each line item must have a description.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            'voucher_number.unique' => 'This voucher number already exists for another voucher.',
            'bank_activity_id.required' => 'Destination bank selection is required.',
            'total_amount.required' => 'Total amount is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Handle FormData arrays - convert to proper format if needed
        $data = $this->all();
        
        // Ensure total_amount is set - check if it's in the request
        if (!isset($data['total_amount']) && isset($data['items'])) {
            // If total_amount is not in request but items are, calculate it
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
        
        // If document_types is a string (JSON), decode it
        if (isset($data['document_types']) && is_string($data['document_types'])) {
            $data['document_types'] = json_decode($data['document_types'], true);
            $this->merge(['document_types' => $data['document_types']]);
        }
        
        // If documents_to_delete is a string (comma-separated or JSON), convert it
        if (isset($data['documents_to_delete']) && is_string($data['documents_to_delete'])) {
            if (str_contains($data['documents_to_delete'], '[')) {
                // JSON array
                $data['documents_to_delete'] = json_decode($data['documents_to_delete'], true);
            } elseif (str_contains($data['documents_to_delete'], ',')) {
                // Comma-separated string
                $data['documents_to_delete'] = explode(',', $data['documents_to_delete']);
            } else {
                // Single value
                $data['documents_to_delete'] = [$data['documents_to_delete']];
            }
            $this->merge(['documents_to_delete' => $data['documents_to_delete']]);
        }

        // Ensure status is set properly
        if (!isset($data['status'])) {
            $this->merge(['status' => 'Draft']);
        }
        
        // Ensure bank_activity_id is set if not provided
        // if (!isset($data['bank_activity_id']) || empty($data['bank_activity_id'])) {
        //     $this->merge(['bank_activity_id' => null]);
        // }
        
        // Log prepared data for debugging
        \Log::info('Prepared validation data:', [
            'has_total_amount' => isset($this->total_amount),
            'total_amount_value' => $this->total_amount ?? 'NOT SET',
            'items_count' => is_array($this->items) ? count($this->items) : 0,
        ]);
    }
}