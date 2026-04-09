<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'code'     => 'required|string|max:20|unique:banks,code,' . ($this->bank?->id ?? 'NULL'),
            'initials' => 'nullable|string|max:10',
            'status'   => 'required|integer|in:0,1',
        ];
    }
}
