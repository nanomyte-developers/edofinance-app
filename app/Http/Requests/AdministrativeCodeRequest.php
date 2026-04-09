<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdministrativeCodeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Get the ID from the model
        $id = $this->route('administrative_code')->id ?? null;
        
        return [
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:administrative_codes,code,' . ($id ?: 'NULL'),
            'status' => 'required|boolean',
        ];
    }
}
