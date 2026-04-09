<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VoucherDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'document_type' => $this->document_type,
            'document_label' => $this->document_label,
            'document_type_label' => $this->document_type_label, // Use the accessor
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'url' => Storage::url($this->file_path),
            'extension' => $this->extension,
            'is_image' => $this->is_image,
            'is_pdf' => $this->is_pdf,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}