<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashBookBalanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bank_activity_id' => 'required|integer',
            'financial_year' => 'sometimes|required',
            'amount' => 'required',
            'status' => 'required|integer|in:0,1',
        ];
    }
}
