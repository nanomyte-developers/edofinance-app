<?php

namespace App\Http\Requests;

use App\Models\RetirementVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateRetirementStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $retirementVoucher = $this->route('retirementVoucher')
            ?? RetirementVoucher::find($this->input('retirement_voucher_id'));

        if (! $retirementVoucher) {
            return false;
        }

        // Check if user can approve/reject retirement vouchers
        return Gate::allows('approveRetirement', $retirementVoucher);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'in:approved,rejected',
                function ($attribute, $value, $fail) {
                    $retirementVoucher = $this->route('retirementVoucher');
                    if (! $retirementVoucher) {
                        return;
                    }

                    // Check if retirement can be approved/rejected
                    if (! $retirementVoucher->canBeApproved()) {
                        $fail('This retirement voucher cannot be approved or rejected in its current status.');
                    }

                    // Additional business rules
                    if ($value === 'approved') {
                        // Check if voucher is already fully retired
                        $originalVoucher = $retirementVoucher->originalVoucher;
                        if ($originalVoucher && $originalVoucher->is_fully_retired) {
                            $fail('The original voucher is already fully retired.');
                        }
                    }
                },
            ],

            'comment' => [
                'required_if:status,rejected',
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) {
                    if ($this->input('status') === 'rejected' && empty($value)) {
                        $fail('A comment is required when rejecting a retirement voucher.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either approved or rejected',
            'comment.required_if' => 'A comment is required when rejecting a retirement voucher',
            'comment.max' => 'Comment cannot exceed 1000 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert status to lowercase for consistency
        if ($this->has('status')) {
            $this->merge([
                'status' => strtolower($this->input('status')),
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Add additional data
        $validated['action_at'] = now();

        return $validated;
    }
}
