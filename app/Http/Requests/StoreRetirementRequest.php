<?php

namespace App\Http\Requests;

use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Log;

class StoreRetirementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Get the voucher from route model binding
        $voucher = $this->route('voucher');

        if (! $voucher) {
            return false;
        }

        // Check if user can retire this voucher
        // return Gate::allows('retire', $voucher);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    // public function rules(): array
    // {
    //     return [
    //         'line_items' => 'required|array|min:1',
    //         'line_items.*.description' => 'required|string|max:255',
    //         'line_items.*.economic_code_id' => 'required|exists:economy_codes,id',
    //         'line_items.*.code_item_id' => 'required|exists:economy_code_items,id', // Changed from economic_code_item_id to match frontend
    //         'line_items.*.quantity' => 'required|numeric|min:0.01',
    //         'line_items.*.unit_price' => 'required|numeric|min:0',
    //         'line_items.*.sub_total' => 'required|numeric|min:0',

    //         'total_amount' => [
    //             'required',
    //             'numeric',
    //             'min:0',
    //             function ($attribute, $value, $fail) {
    //                 $voucher = $this->route('voucher');

    //                 if (! $voucher) {
    //                     $fail('Voucher not found.');

    //                     return;
    //                 }

    //                 // Check voucher type
    //                 if ($voucher->voucher_type !== 'prepayment') {
    //                     $fail('Only prepayment vouchers can be retired.');
    //                 }

    //                 // Check voucher status
    //                 if ($voucher->status !== 'approved') {
    //                     $fail('Only approved vouchers can be retired.');
    //                 }

    //                 // Check if already fully retired
    //                 if ($voucher->is_fully_retired) {
    //                     $fail('This voucher is already fully retired.');
    //                 }

    //                 // Calculate available balance
    //                 $availableBalance = $voucher->amount - ($voucher->retired_amount ?? 0); // Adjust column names as needed

    //                 if ($value > $availableBalance) {
    //                     $fail('Retirement amount (₦'.number_format($value, 2).
    //                           ') exceeds available balance (₦'.number_format($availableBalance, 2).')');
    //                 }

    //                 if ($value <= 0) {
    //                     $fail('Retirement amount must be greater than 0.');
    //                 }
    //             },
    //         ],

    //         'comment' => 'nullable|string|max:1000',
    //         'remaining_balance' => 'required|numeric|min:0',
    //         'is_partial' => 'boolean',
    //         'schedule_id' => 'nullable|exists:schedules,id',
    //         'year_id' => 'nullable|exists:financial_years,id',
    //         'mda_id' => 'nullable|exists:mdas,id',
    //         'bank_activity_id' => 'nullable|exists:bank_activities,id',
    //     ];
    // }

    public function rules(): array
    {
        return [
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:255',
            'line_items.*.economic_code_id' => 'required|exists:economy_codes,id',
            'line_items.*.code_item_id' => 'required|exists:economy_code_items,id',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.sub_total' => 'required|numeric|min:0',

            'total_amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $voucher = $this->route('voucher');

                    if (! $voucher) {
                        $fail('Voucher not found.');

                        return;
                    }

                    // Log for debugging
                    Log::info('Retirement validation:', [
                        'voucher_id' => $voucher->id,
                        'voucher_amount' => $voucher->total_amount,
                        'retired_amount' => $voucher->retired_amount,
                        'voucher_status' => $voucher->status,
                        'voucher_type' => $voucher->voucher_type,
                        'retirement_amount' => $value,
                        'available_balance' => $voucher->total_amount - ($voucher->retired_amount ?? 0),
                    ]);

                    // TEMPORARILY DISABLE FOR DEBUGGING - Comment out these checks
                    /*
                    // Check voucher type
                    if ($voucher->voucher_type !== 'prepayment') {
                        $fail('Only prepayment vouchers can be retired.');
                        return;
                    }

                    // Check voucher status
                    if ($voucher->status !== 'approved') {
                        $fail('Only approved vouchers can be retired.');
                        return;
                    }

                    // Check if already fully retired
                    if ($voucher->is_fully_retired) {
                        $fail('This voucher is already fully retired.');
                        return;
                    }
                    */

                    // Calculate available balance
                    $availableBalance = $voucher->total_amount - ($voucher->retired_amount ?? 0);

                    if ($value > $availableBalance) {
                        $fail('Retirement amount (₦'.number_format($value, 2).
                              ') exceeds available balance (₦'.number_format($availableBalance, 2).')');

                        return;
                    }

                    if ($value <= 0) {
                        $fail('Retirement amount must be greater than 0.');

                        return;
                    }
                },
            ],

            'comment' => 'nullable|string|max:1000',
            'remaining_balance' => 'required|numeric',
            'is_partial' => 'boolean',
            'schedule_id' => 'nullable|exists:schedules,id',
            'year_id' => 'nullable|exists:financial_years,id',
            'mda_id' => 'nullable|exists:mdas,id',
            'bank_activity_id' => 'nullable|exists:bank_activities,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'line_items.required' => 'At least one line item is required',
            'line_items.*.description.required' => 'Description is required for all items',
            'line_items.*.economic_code_id.required' => 'Economic code is required for all items',
            'line_items.*.code_item_id.required' => 'Code item is required for all items',
            'line_items.*.quantity.required' => 'Quantity is required for all items',
            'line_items.*.quantity.min' => 'Quantity must be at least 0.01',
            'line_items.*.unit_price.required' => 'Unit price is required for all items',
            'line_items.*.unit_price.min' => 'Unit price cannot be negative',
            'line_items.*.sub_total.required' => 'Sub total is required for all items',
            'line_items.*.sub_total.min' => 'Sub total cannot be negative',
            'total_amount.required' => 'Total amount is required',
            'total_amount.min' => 'Total amount must be greater than 0',
            'remaining_balance.required' => 'Remaining balance is required',
            'remaining_balance.min' => 'Remaining balance cannot be negative',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Calculate total from line items if not provided or validate
        if ($this->has('line_items')) {
            $total = collect($this->line_items)->sum('sub_total');

            // If total_amount is provided, use it, otherwise calculate
            if (! $this->has('total_amount') || $this->total_amount == 0) {
                $this->merge(['total_amount' => $total]);
            }

            // Ensure all line items have positive values
            $lineItems = collect($this->line_items)->map(function ($item) {
                return array_merge($item, [
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'sub_total' => (float) $item['sub_total'],
                ]);
            })->toArray();

            $this->merge(['line_items' => $lineItems]);
        }

        // Ensure numeric values
        $this->merge([
            'total_amount' => (float) ($this->total_amount ?? 0),
            'remaining_balance' => (float) ($this->remaining_balance ?? 0),
            'is_partial' => (bool) ($this->is_partial ?? false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    // public function withValidator($validator): void
    // {
    //     $validator->after(function ($validator) {
    //         $voucher = $this->route('voucher');

    //         if (! $voucher) {
    //             $validator->errors()->add('voucher', 'Voucher not found.');

    //             return;
    //         }

    //         // Validate sub_total matches quantity * unit_price
    //         if ($this->has('line_items')) {
    //             foreach ($this->line_items as $index => $item) {
    //                 $calculated = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
    //                 $entered = $item['sub_total'] ?? 0;
    //                 $difference = abs($calculated - $entered);

    //                 if ($difference > 0.01) { // Allow small floating point differences
    //                     $validator->errors()->add(
    //                         "line_items.$index.sub_total",
    //                         'Sub total does not match quantity × unit price (Calculated: ₦'.
    //                         number_format($calculated, 2).', Entered: ₦'.
    //                         number_format($entered, 2).')'
    //                     );
    //                 }
    //             }
    //         }

    //         // Validate that total_amount matches sum of line items
    //         if ($this->has('line_items') && $this->has('total_amount')) {
    //             $calculatedTotal = collect($this->line_items)->sum('sub_total');
    //             $enteredTotal = $this->total_amount;
    //             $difference = abs($calculatedTotal - $enteredTotal);

    //             if ($difference > 0.01) {
    //                 $validator->errors()->add(
    //                     'total_amount',
    //                     'Total amount (₦'.number_format($enteredTotal, 2).
    //                     ') does not match sum of line items (₦'.number_format($calculatedTotal, 2).')'
    //                 );
    //             }
    //         }

    //         // Validate remaining balance logic
    //         if ($this->has('total_amount') && $this->has('remaining_balance') && $voucher) {
    //             $voucherAmount = $voucher->amount;
    //             $retiredAmount = $this->total_amount;
    //             $remainingBalance = $this->remaining_balance;
    //             $expectedRemaining = $voucherAmount - $retiredAmount;

    //             if (abs($remainingBalance - $expectedRemaining) > 0.01) {
    //                 $validator->errors()->add(
    //                     'remaining_balance',
    //                     'Remaining balance calculation is incorrect. Expected: ₦'.
    //                     number_format($expectedRemaining, 2).', Provided: ₦'.
    //                     number_format($remainingBalance, 2)
    //                 );
    //             }
    //         }
    //     });
    // }
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $voucher = $this->route('voucher');

            if (! $voucher) {
                $validator->errors()->add('voucher', 'Voucher not found.');

                return;
            }

            // Log voucher details for debugging
            \Log::info('Voucher details for remaining balance check:', [
                'voucher_id' => $voucher->id,
                'amount' => $voucher->amount,
                'retired_amount' => $voucher->retired_amount,
                'total_amount' => $this->total_amount,
                'remaining_balance' => $this->remaining_balance,
                'calculated_remaining' => $voucher->amount - $this->total_amount,
            ]);

            // TEMPORARILY DISABLE THIS CHECK FOR DEBUGGING
            /*
            // Validate remaining balance logic
            if ($this->has('total_amount') && $this->has('remaining_balance') && $voucher) {
                $voucherAmount = $voucher->amount;
                $retiredAmount = $this->total_amount;
                $remainingBalance = $this->remaining_balance;
                $expectedRemaining = $voucherAmount - $retiredAmount;

                \Log::info('Remaining balance calculation:', [
                    'voucherAmount' => $voucherAmount,
                    'retiredAmount' => $retiredAmount,
                    'remainingBalance' => $remainingBalance,
                    'expectedRemaining' => $expectedRemaining
                ]);

                if (abs($remainingBalance - $expectedRemaining) > 0.01) {
                    $validator->errors()->add(
                        'remaining_balance',
                        'Remaining balance calculation is incorrect. Expected: ₦'.
                        number_format($expectedRemaining, 2).', Provided: ₦'.
                        number_format($remainingBalance, 2)
                    );
                }
            }
            */

            // Validate sub_total matches quantity * unit_price
            if ($this->has('line_items')) {
                foreach ($this->line_items as $index => $item) {
                    $calculated = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $entered = $item['sub_total'] ?? 0;
                    $difference = abs($calculated - $entered);

                    if ($difference > 0.01) {
                        $validator->errors()->add(
                            "line_items.$index.sub_total",
                            'Sub total does not match quantity × unit price (Calculated: ₦'.
                            number_format($calculated, 2).', Entered: ₦'.
                            number_format($entered, 2).')'
                        );
                    }
                }
            }

            // Validate that total_amount matches sum of line items
            if ($this->has('line_items') && $this->has('total_amount')) {
                $calculatedTotal = collect($this->line_items)->sum('sub_total');
                $enteredTotal = $this->total_amount;
                $difference = abs($calculatedTotal - $enteredTotal);

                if ($difference > 0.01) {
                    $validator->errors()->add(
                        'total_amount',
                        'Total amount (₦'.number_format($enteredTotal, 2).
                        ') does not match sum of line items (₦'.number_format($calculatedTotal, 2).')'
                    );
                }
            }
        });
    }
}
