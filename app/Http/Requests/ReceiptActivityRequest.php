<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('receipt_activity')?->id ?? $this->route('receipt_activity');
        
        return [
            'name' => 'required|string|max:255',
            'status' => 'required|boolean', // Accepts 0/1 or true/false
        ];
    }
}
