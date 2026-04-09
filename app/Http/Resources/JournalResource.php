<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_number' => $this->journal_number,
            'journal_date' => $this->journal_date,
            'posting_date' => $this->posting_date,
            'description' => $this->description,
            'remarks' => $this->remarks,
            'total_amount' => (float) $this->total_amount,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'status' => $this->status,
            'reference_number' => $this->reference_number,
            'source_document' => $this->source_document,
            'batch_number' => $this->batch_number,
            'financial_year' => $this->financial_year,
            'is_recurring' => $this->is_recurring,
            'recurring_frequency' => $this->recurring_frequency,
            'next_recurring_date' => $this->next_recurring_date,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'code' => $this->department->code,
                ];
            }),

            'gl_category' => $this->whenLoaded('glCategory', function () {
                return [
                    'id' => $this->glCategory->id,
                    'category_code' => $this->glCategory->category_code,
                    'category_name' => $this->glCategory->category_name,
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                    'email' => $this->approver->email,
                ] : null;
            }),

            'entries' => $this->whenLoaded('entries', function () {
                return $this->entries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'account_code' => $entry->account_code,
                        'account_name' => $entry->account_name,
                        'description' => $entry->description,
                        'debit_amount' => (float) $entry->debit_amount,
                        'credit_amount' => (float) $entry->credit_amount,
                        'line_number' => $entry->line_number,
                        'cost_center' => $entry->cost_center,
                        'project_code' => $entry->project_code,
                        'reference' => $entry->reference,
                        'tax_code' => $entry->tax_code,
                        'tax_amount' => (float) $entry->tax_amount,
                        'net_amount' => (float) $entry->net_amount,
                        'entry_type' => $entry->entry_type,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,

                        'gl_account' => $entry->whenLoaded('glAccount', function () use ($entry) {
                            return [
                                'id' => $entry->glAccount->id,
                                'account_code' => $entry->glAccount->account_code,
                                'account_name' => $entry->glAccount->account_name,
                                'account_type' => $entry->glAccount->account_type,
                                'normal_balance' => $entry->glAccount->normal_balance,
                                'current_balance' => (float) $entry->glAccount->current_balance,
                                'formatted_type' => $entry->glAccount->formatted_account_type,
                            ];
                        }),
                    ];
                });
            }),

            // Computed fields
            'formatted_journal_date' => $this->journal_date ? date('d M, Y', strtotime($this->journal_date)) : 'N/A',
            'formatted_posting_date' => $this->posting_date ? date('d M, Y', strtotime($this->posting_date)) : 'N/A',
            'formatted_amount' => '₦'.number_format($this->total_amount, 2),
            'formatted_debit' => '₦'.number_format($this->total_debit, 2),
            'formatted_credit' => '₦'.number_format($this->total_credit, 2),
            'formatted_approved_at' => $this->approved_at ? $this->approved_at->format('d M, Y H:i') : 'N/A',
            'formatted_created_at' => $this->created_at ? $this->created_at->format('d M, Y H:i') : 'N/A',

            // Permission flags
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'is_balanced' => $this->isBalanced(),
            'balance_difference' => abs($this->total_debit - $this->total_credit),

            // Status information
            'status_severity' => $this->getStatusSeverity(),
            'is_approved' => $this->status === 'approved',
            'is_pending' => $this->status === 'pending',
            'is_draft' => $this->status === 'draft',
            'is_rejected' => $this->status === 'rejected',
        ];
    }

    /**
     * Get status severity for UI display.
     */
    private function getStatusSeverity(): string
    {
        $status = strtolower($this->status);

        switch ($status) {
            case 'draft':
            case 'saved':
                return 'warning';
            case 'pending':
            case 'submitted':
                return 'info';
            case 'approved':
            case 'completed':
            case 'posted':
                return 'success';
            case 'declined':
            case 'rejected':
            case 'failed':
            case 'returned':
            case 'sent back':
            case 'cancelled':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}
