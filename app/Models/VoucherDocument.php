<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VoucherDocument extends Model
{
    protected $fillable = [
        'voucher_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'document_type',
        'document_label', // Added this field
        'description',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Relationship with Voucher
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Relationship with Uploader
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Get the full URL for the document
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get file extension
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Check if document is an image
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if document is a PDF
     */
    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get human-readable document type
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            'approval_form' => 'Approval Form',
            'invoice' => 'Invoice',
            'receipt' => 'Receipt',
            'delivery_note' => 'Delivery Note',
            'other' => 'Additional Document',
            'supporting' => 'Supporting Document',
        ];

        return $labels[$this->document_type] ?? $this->document_label ?? 'Supporting Document';
    }

    /**
     * Scope for required documents
     */
    public function scopeRequired($query)
    {
        return $query->whereIn('document_type', ['approval_form', 'invoice', 'receipt', 'delivery_note']);
    }

    /**
     * Scope for optional documents
     */
    public function scopeOptional($query)
    {
        return $query->where('document_type', 'other');
    }

    /**
     * Scope for specific document type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }
}