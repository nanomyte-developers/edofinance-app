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

    // /**
    //  * Get human-readable document type
    //  */
    // public function getDocumentTypeLabelAttribute(): string
    // {
    //     $labels = [
    //         'approval_form' => 'Approval Form',
    //         'invoice' => 'Invoice',
    //         'receipt' => 'Receipt',
    //         'delivery_note' => 'Delivery Note',
    //         'other' => 'Additional Document',
    //         'supporting' => 'Supporting Document',
    //     ];

    //     return $labels[$this->document_type] ?? $this->document_label ?? 'Supporting Document';
    // }

    // /**
    //  * Scope for required documents
    //  */
    // public function scopeRequired($query)
    // {
    //     return $query->whereIn('document_type', ['approval_form', 'invoice', 'receipt', 'delivery_note']);
    // }

    // /**
    //  * Scope for optional documents
    //  */
    // public function scopeOptional($query)
    // {
    //     return $query->where('document_type', 'other');
    // }

    // /**
    //  * Scope for specific document type
    //  */
    // public function scopeOfType($query, $type)
    // {
    //     return $query->where('document_type', $type);
    // }

    /**
     * Get the document type label attribute
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            // Required/Supporting Documents
            'approval_memo' => 'Approval Memo',
            'release_warrant' => 'Release Warrant',
            'exco_approval' => 'Exco Approval/Conclusion',
            'ministerial_tender_board' => 'Ministerial Tender Board',
            'state_tender_board' => 'State Tenders Board',
            
            // Business/Registration Documents
            'certificate_of_incorporation' => 'Certificate Of Incorporation',
            'tax_clearance' => 'Tax Clearance',
            'tin' => 'Tax Identification Number (TIN)',
            'procurement_registration' => 'Procurement Registration',
            
            // Financial Documents
            'advance_payment_guarantee' => 'Advance Payment Guarantee (APG)',
            'receipt' => 'Receipt',
            'delivery_note' => 'Delivery Note',
            
            // Default/Other
            'other' => 'Additional Document',
            'supporting' => 'Supporting Document',
        ];

        return $labels[$this->document_type] ?? $this->document_label ?? 'Supporting Document';
    }

    /**
     * Scope for required documents
     * Documents that are required for voucher submission
     */
    public function scopeRequired($query)
    {
        return $query->whereIn('document_type', [
            'approval_memo',
            'release_warrant',
            'exco_approval',
            'ministerial_tender_board',
            'state_tender_board',
            'certificate_of_incorporation',
            'tax_clearance',
            'tin',
            'procurement_registration',
            'advance_payment_guarantee',
            'receipt',
            'delivery_note'
        ]);
    }

    /**
     * Scope for optional documents
     */
    public function scopeOptional($query)
    {
        return $query->whereIn('document_type', ['other', 'supporting']);
    }

    /**
     * Scope for specific document type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope for approval documents
     */
    public function scopeApprovalDocuments($query)
    {
        return $query->whereIn('document_type', [
            'approval_memo',
            'exco_approval',
            'ministerial_tender_board',
            'state_tender_board'
        ]);
    }

    /**
     * Scope for financial documents
     */
    public function scopeFinancialDocuments($query)
    {
        return $query->whereIn('document_type', [
            'receipt',
            'delivery_note',
            'advance_payment_guarantee'
        ]);
    }

    /**
     * Scope for registration documents
     */
    public function scopeRegistrationDocuments($query)
    {
        return $query->whereIn('document_type', [
            'certificate_of_incorporation',
            'tax_clearance',
            'tin',
            'procurement_registration'
        ]);
    }

}