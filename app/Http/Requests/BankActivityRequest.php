<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tag' => 'required|string|max:10',
            'bank_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:20',
            'status' => 'required|integer|in:0,1',
            'economic_code' => 'required|string|max:10',
            'balanceBFW' => 'required|numeric',
        ];
    }
}
