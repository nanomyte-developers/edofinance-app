<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Support\Str;
use App\Models\VoucherApproval;
use App\Models\VoucherDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoucherService
{
    /**
     * Create a new voucher with line items and documents
     */
    public function createVoucher(array $data, array $files = [], array $documentTypes = [])
    {
        return DB::transaction(function () use ($data, $files, $documentTypes) {
            try {
                Log::info('=== VOUCHER CREATION STARTED ===', [
                    'voucher_number' => $data['voucher_number'],
                    'schedule_id' => $data['schedule_id'] ?? null,
                    'voucher_type' => $data['voucher_type'] ?? 'standard',
                    'files_count' => count($files),
                    'document_types_count' => count($documentTypes),
                ]);

                // Calculate total amount from items
                $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

                // Create the main voucher with schedule_id
                $voucherData = [
                    'voucher_number' => strtoupper($data['voucher_number']),
                    'year_id' => $data['year_id'],
                    'mda_id' => $data['mda_id'],
                    'voucher_date' => $data['voucher_date'],
                    'narration' => $data['narration'],
                    'payee_name' => $data['payee_name'],
                    'total_amount' => $totalAmount ?? 0,
                    'status' => $data['status'] ?? 'Draft',
                    'voucher_type' => $data['voucher_type'] ?? 'standard',
                    'created_by_user_id' => auth()->id(),
                    'bank_activity_id' => $data['bank_activity_id'] ?? null,
                ];

                // Add schedule_id if provided
                if (isset($data['schedule_id']) && !empty($data['schedule_id'])) {
                    $voucherData['schedule_id'] = $data['schedule_id'];
                }

                $voucher = Voucher::create($voucherData);

                Log::info('Voucher created:', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'schedule_id' => $voucher->schedule_id,
                    'voucher_type' => $voucher->voucher_type,
                ]);

                // Create line items if provided
                if (isset($data['items']) && is_array($data['items'])) {
                    $this->createLineItems($voucher, $data['items']);
                    Log::info('Line items created:', ['count' => count($data['items'])]);
                }

                // Handle file uploads if provided
                if (!empty($files)) {
                    $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
                }

                // Create initial approval record
                $this->createInitialApproval($voucher);

                // Reload voucher with relationships
                $voucher->load('documents');

                Log::info('=== VOUCHER CREATION COMPLETED ===', [
                    'voucher_id' => $voucher->id,
                    'schedule_id' => $voucher->schedule_id,
                    'voucher_type' => $voucher->voucher_type,
                    'documents_created' => $voucher->documents->count(),
                ]);

                return $voucher->load([
                    'mda',
                    'financialYear',
                    'schedule',
                    'items',
                    'documents',
                    'approvals.user',
                    'creator'
                ]);
            } catch (\Exception $e) {
                Log::error('VoucherService Transaction Failed: ' . $e->getMessage(), [
                    'data' => $data,
                    'schedule_id' => $data['schedule_id'] ?? null,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update an existing voucher
     */
    public function updateVoucher(Voucher $voucher, array $data, array $files = [], array $documentTypes = [])
    {
        DB::beginTransaction();

        try {
            $data = $this->checkRetirementStatus($voucher, $data);

            Log::info('Updating voucher with programme data:', [
                'voucher_id' => $voucher->id,
                'items_count' => isset($data['items']) ? count($data['items']) : 0,
            ]);

            // Update voucher details
            $voucher->update([
                'voucher_type' => $data['voucher_type'],
                'year_id' => $data['year_id'],
                'mda_id' => $data['mda_id'],
                'voucher_date' => $data['voucher_date'],
                'narration' => $data['narration'],
                'status' => $data['status'],
                'total_amount' => $data['total_amount'],
                'payee_name' => $data['payee_name'],
                'bank_activity_id' => $data['bank_activity_id'] ?? null,
                'voucher_number' => $data['voucher_number'],
            ]);

            // Sync items - Delete existing and create new with programme code fields
            $voucher->items()->delete();
            
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    Log::info('Creating voucher item with programme data:', [
                        'description' => $itemData['description'],
                        'programme_code_id' => $itemData['programme_code_id'] ?? null,
                        'programme_code' => $itemData['programme_code'] ?? null,
                        'programme_name' => $itemData['programme_name'] ?? null,
                        'budget_code' => $itemData['budget_code'] ?? null,
                    ]);
                    
                    $voucher->items()->create([
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'sub_total' => $itemData['sub_total'],
                        'economy_code_id' => $itemData['economy_code_id'] ?? null,
                        'economy_code_item_id' => $itemData['economy_code_item_id'] ?? null,
                        'programme_code_id' => $itemData['programme_code_id'] ?? null,
                        'programme_code' => $itemData['programme_code'] ?? null,
                        'programme_name' => $itemData['programme_name'] ?? null,
                        'budget_code' => $itemData['budget_code'] ?? null,
                    ]);
                }
            }

            // Handle document deletions if documents_to_delete exists
            if (isset($data['documents_to_delete']) && !empty($data['documents_to_delete'])) {
                foreach ($data['documents_to_delete'] as $docId) {
                    $document = VoucherDocument::find($docId);
                    if ($document) {
                        if (Storage::exists($document->file_path)) {
                            Storage::delete($document->file_path);
                        }
                        $document->delete();
                    }
                }
            }

            // Handle new document uploads ONLY if files exist
            if (!empty($files)) {
                foreach ($files as $index => $documentFile) {
                    if ($documentFile->isValid()) {
                        $path = $documentFile->store('voucher_documents', 'public');

                        $documentType = $documentTypes[$index]['type'] ?? 'other';
                        $documentLabel = $documentTypes[$index]['label'] ?? $documentType;

                        $voucher->documents()->create([
                            'file_name' => $documentFile->getClientOriginalName(),
                            'file_path' => $path,
                            'file_type' => $documentFile->getMimeType(),
                            'file_size' => $documentFile->getSize(),
                            'document_type' => $documentType,
                            'document_label' => $documentLabel,
                            'uploaded_by' => auth()->id(),
                        ]);
                    }
                }
            }

            DB::commit();
            
            // Reload the voucher with items to verify
            $voucher->load('items');
            Log::info('Voucher updated successfully, items count: ' . $voucher->items->count());
            
            return $voucher->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Voucher update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create line items for voucher
     */
    protected function createLineItems(Voucher $voucher, array $items): void
    {
        foreach ($items as $item) {
            Log::info('Creating line item with programme data:', [
                'description' => $item['description'],
                'programme_code_id' => $item['programme_code_id'] ?? null,
                'programme_code' => $item['programme_code'] ?? null,
                'programme_name' => $item['programme_name'] ?? null,
                'budget_code' => $item['budget_code'] ?? null,
            ]);
            
            VoucherItem::create([
                'voucher_id' => $voucher->id,
                'description' => $item['description'],
                'economy_code_id' => $item['economy_code_id'] ?? null,
                'economy_code_item_id' => $item['economy_code_item_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total'],
                'budget_code' => $item['budget_code'] ?? null,
                'programme_code_id' => $item['programme_code_id'] ?? null,
                'programme_code' => $item['programme_code'] ?? null,
                'programme_name' => $item['programme_name'] ?? null,
            ]);
        }
    }

    /**
     * Update line items for voucher
     */
    protected function updateLineItems(Voucher $voucher, array $items): void
    {
        // Delete existing line items
        $voucher->items()->delete();

        // Create new line items
        $this->createLineItems($voucher, $items);
    }

    /**
     * Calculate total amount from line items
     */
    protected function calculateTotalAmount(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            if (isset($item['sub_total']) && is_numeric($item['sub_total'])) {
                $total += (float) $item['sub_total'];
            } elseif (isset($item['quantity']) && isset($item['unit_price'])) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $total += $quantity * $unitPrice;
            }
        }

        return round($total, 2);
    }

    /**
     * Mark a prepayment voucher as retired
     */
    public function retirePrepaymentVoucher(Voucher $voucher, ?Voucher $retirementVoucher = null): bool
    {
        if (!$voucher->is_prepayment || !$voucher->requires_retirement) {
            throw new \Exception('This voucher does not require retirement.');
        }

        if (!$voucher->is_approved) {
            throw new \Exception('Only approved vouchers can be retired.');
        }

        if ($voucher->is_retired) {
            throw new \Exception('This voucher is already retired.');
        }

        return DB::transaction(function () use ($voucher, $retirementVoucher) {
            $updateData = [
                'retired_at' => now(),
                'status' => Voucher::STATUS_RETIRED,
            ];

            if ($retirementVoucher) {
                $updateData['retirement_voucher_id'] = $retirementVoucher->id;
            }

            return $voucher->update($updateData);
        });
    }

    /**
     * Delete voucher and related records
     */
    public function deleteVoucher(Voucher $voucher): bool
    {
        return DB::transaction(function () use ($voucher) {
            // Delete related records
            $voucher->items()->delete();
            $voucher->documents()->delete();
            $voucher->approvals()->delete();

            // Delete the voucher
            return $voucher->delete();
        });
    }

    /**
     * Create initial approval record
     */
    protected function createInitialApproval(Voucher $voucher): void
    {
        $status = $voucher->status === 'Submitted' ? 'pending' : 'draft';
        $action = $voucher->status === 'Submitted' ? 'Forwarded' : 'Saved';
        $comment = $voucher->status === 'Submitted' ? 'Submitted for approval' : 'Saved as draft';

        VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => auth()->id(),
            'approval_role' => 'Creator',
            'approval_step' => 1,
            'action' => $action,
            'status' => $status,
            'comment' => $comment,
            'action_at' => now(),
            'approval_level' => 1,
        ]);
    }

    /**
     * Create next approval step in workflow
     */
    public function createNextApproval(Voucher $voucher, array $data): VoucherApproval
    {
        $currentStep = $voucher->approvals()->max('approval_step') ?? 0;
        $nextStep = $currentStep + 1;

        return VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => $data['user_id'],
            'approval_role' => $data['role'],
            'approval_step' => $nextStep,
            'action' => 'Forwarded',
            'status' => 'pending',
            'comment' => $data['comment'] ?? 'Forwarded for next approval',
            'action_at' => now(),
            'approval_level' => $data['level'] ?? $nextStep,
            'next_approval_user_id' => $data['next_user_id'] ?? null,
        ]);
    }

    /**
     * Update voucher total amount
     */
    public function updateVoucherTotal(Voucher $voucher): void
    {
        $totalAmount = $voucher->items()->sum('sub_total');
        $voucher->update(['total_amount' => $totalAmount]);
    }

    /**
     * Decline and close voucher
     */
    public function declineAndCloseVoucher(Voucher $voucher, string $comment): VoucherApproval
    {
        $currentStep = $voucher->approvals()->max('approval_step') ?? 0;
        $nextStep = $currentStep + 1;

        return VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => auth()->id(),
            'approval_role' => 'Accountant General',
            'approval_step' => $nextStep,
            'action' => 'Decline and Close',
            'status' => 'closed',
            'comment' => $comment,
            'action_at' => now(),
            'approval_level' => $nextStep,
        ]);
    }

    /**
     * Check retirement status
     */
    public function checkRetirementStatus(Voucher $voucher, array $data): array
    {
        if ($voucher->retirementVoucher != null) {
            if (strtolower($voucher->retirementVoucher->status) === 'submitted') {
                $data['status'] = 'Approved';
            }
        }
        return $data;
    }

    /**
     * Handle document uploads with guaranteed type assignment
     */
    protected function handleDocumentUploadsNew(Voucher $voucher, array $files, array $documentTypes = []): void
    {
        Log::info('=== DOCUMENT UPLOAD PROCESSING START ===', [
            'voucher_id' => $voucher->id,
            'total_files' => count($files),
            'document_types_received' => $documentTypes,
            'file_names' => array_map(fn($file) => $file->getClientOriginalName(), $files)
        ]);

        // Create a mapping of filename to document type from the frontend
        $fileTypeMapping = $this->createFileTypeMapping($files, $documentTypes);

        Log::info('File to type mapping created:', $fileTypeMapping);

        foreach ($files as $file) {
            $filename = $file->getClientOriginalName();
            $path = $file->store('voucher-documents/' . $voucher->id, 'public');

            // Get the document type from our mapping
            $documentData = $this->getDocumentTypeFromMapping($filename, $fileTypeMapping);

            Log::info('Creating document record:', [
                'file' => $filename,
                'type' => $documentData['type'],
                'label' => $documentData['label'],
                'method' => $documentData['method']
            ]);

            VoucherDocument::create([
                'voucher_id' => $voucher->id,
                'file_name' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'document_type' => $documentData['type'],
                'document_label' => $documentData['label'],
                'uploaded_by_user_id' => auth()->id(),
            ]);
        }

        Log::info('=== DOCUMENT UPLOAD PROCESSING COMPLETE ===', [
            'voucher_id' => $voucher->id,
            'documents_created' => count($files)
        ]);
    }

    /**
     * Create a mapping of filename to document type
     */
    protected function createFileTypeMapping(array $files, array $documentTypes): array
    {
        $mapping = [];
        $fileNames = array_map(fn($file) => $file->getClientOriginalName(), $files);

        Log::info('Creating file type mapping:', [
            'available_files' => $fileNames,
            'document_types' => $documentTypes
        ]);

        // Strategy 1: Direct filename matching from document_types
        foreach ($documentTypes as $docType) {
            if (isset($docType['file_name']) && in_array($docType['file_name'], $fileNames)) {
                $mapping[$docType['file_name']] = [
                    'type' => $docType['type'] ?? 'other',
                    'label' => $docType['label'] ?? $this->getDocumentTypeLabel($docType['type'] ?? 'other'),
                    'method' => 'direct_filename_match'
                ];
                Log::info('Direct filename match found:', [
                    'file' => $docType['file_name'],
                    'type' => $docType['type']
                ]);
            }
        }

        // Strategy 2: Required document type assignment for unmatched files
        $requiredTypes = ['approval_form', 'invoice', 'receipt', 'delivery_note'];
        $assignedRequiredTypes = [];

        // Get already assigned required types from mapping
        foreach ($mapping as $fileData) {
            if (in_array($fileData['type'], $requiredTypes)) {
                $assignedRequiredTypes[] = $fileData['type'];
            }
        }

        // Assign remaining required types to unmatched files
        foreach ($fileNames as $fileName) {
            if (!isset($mapping[$fileName])) {
                foreach ($requiredTypes as $requiredType) {
                    if (!in_array($requiredType, $assignedRequiredTypes)) {
                        // Find the document type data for this required type
                        $docTypeData = $this->findDocumentTypeData($requiredType, $documentTypes);

                        $mapping[$fileName] = [
                            'type' => $requiredType,
                            'label' => $docTypeData['label'] ?? $this->getDocumentTypeLabel($requiredType),
                            'method' => 'required_type_assignment'
                        ];
                        $assignedRequiredTypes[] = $requiredType;
                        Log::info('Assigned required type to file:', [
                            'file' => $fileName,
                            'type' => $requiredType
                        ]);
                        break;
                    }
                }
            }
        }

        // Strategy 3: For any still unmatched files, use filename inference
        foreach ($fileNames as $fileName) {
            if (!isset($mapping[$fileName])) {
                $inferredType = $this->inferDocumentTypeFromFilename($fileName);
                $mapping[$fileName] = [
                    'type' => $inferredType ?? 'other',
                    'label' => $inferredType ? $this->getDocumentTypeLabel($inferredType) : 'Additional Document',
                    'method' => $inferredType ? 'filename_inference' : 'default_fallback'
                ];
                Log::info('Used inference/fallback for file:', [
                    'file' => $fileName,
                    'type' => $mapping[$fileName]['type']
                ]);
            }
        }

        Log::info('Final file type mapping:', $mapping);
        return $mapping;
    }

    /**
     * Find document type data for a specific type
     */
    protected function findDocumentTypeData(string $type, array $documentTypes): array
    {
        foreach ($documentTypes as $docType) {
            if (isset($docType['type']) && $docType['type'] === $type) {
                return $docType;
            }
        }

        return ['type' => $type, 'label' => $this->getDocumentTypeLabel($type)];
    }

    /**
     * Get document type from mapping
     */
    protected function getDocumentTypeFromMapping(string $filename, array $mapping): array
    {
        if (isset($mapping[$filename])) {
            return $mapping[$filename];
        }

        // Fallback if file not in mapping (shouldn't happen)
        Log::warning('File not found in mapping, using fallback:', ['file' => $filename]);
        return [
            'type' => 'other',
            'label' => 'Additional Document',
            'method' => 'emergency_fallback'
        ];
    }

    /**
     * Handle document deletions
     */
    protected function handleDocumentDeletions(Voucher $voucher, array $documentIds): void
    {
        foreach ($documentIds as $documentId) {
            $document = VoucherDocument::where('voucher_id', $voucher->id)
                ->where('id', $documentId)
                ->first();

            if ($document) {
                // Delete the physical file
                Storage::disk('public')->delete($document->file_path);

                // Delete the database record
                $document->delete();

                Log::info('Document deleted:', [
                    'voucher_id' => $voucher->id,
                    'document_id' => $documentId,
                    'file_name' => $document->file_name,
                ]);
            }
        }
    }

    /**
     * Infer document type from filename patterns
     */
    protected function inferDocumentTypeFromFilename(string $filename): ?string
    {
        $filename = strtolower($filename);

        $patterns = [
            'approval_form' => [
                'approval',
                'approval_form',
                'authorization',
                'auth',
                'approve',
                'authorised',
                'authorized',
                'clearance',
                'endorsement',
                'approved'
            ],
            'invoice' => [
                'invoice',
                'bill',
                'inv_',
                '_inv',
                'billing',
                'statement',
                'charge',
                'fee',
                'quotation',
                'estimate',
                'billed'
            ],
            'receipt' => [
                'receipt',
                'payment',
                'paid',
                'payment_receipt',
                'payment_confirmation',
                'acknowledgement',
                'voucher',
                'payment_voucher',
                'acknowledgment',
                'received'
            ],
            'delivery_note' => [
                'delivery',
                'delivery_note',
                'dispatch',
                'del_note',
                'dn_',
                '_dn',
                'goods_received',
                'grn',
                'waybill',
                'shipping',
                'transport',
                'delivered'
            ],
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($filename, $keyword)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Get human-readable label for document type
     */
    protected function getDocumentTypeLabel(string $type): string
    {
        $labels = [
            'approval_form' => 'Approval Form',
            'invoice' => 'Invoice',
            'receipt' => 'Receipt',
            'delivery_note' => 'Delivery Note',
            'other' => 'Additional Document',
            'supporting' => 'Supporting Document',
        ];

        return $labels[$type] ?? 'Supporting Document';
    }

    /**
     * Recalculate total amount from line items
     */
    protected function recalculateTotalAmount(Voucher $voucher): void
    {
        $totalAmount = $voucher->items()->sum('sub_total');
        $voucher->update(['total_amount' => $totalAmount]);
    }
    
    /**
    * Create a final accounts voucher (direct approval, no workflow)
    */
    // public function createFinalAccountsVoucher(array $data, array $files = [], array $documentTypes = [])
    // {
    //     return DB::transaction(function () use ($data, $files, $documentTypes) {
    //         try {
    //             Log::info('=== FINAL ACCOUNTS VOUCHER CREATION STARTED ===', [
    //                 'voucher_number' => $data['voucher_number'],
    //                 'voucher_type' => $data['voucher_type'] ?? 'standard',
    //             ]);

    //             // Calculate total amount from items
    //             $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

    //             // Create the main voucher with Approved status
    //             $voucherData = [
    //                 'voucher_number' => strtoupper($data['voucher_number']),
    //                 'year_id' => $data['year_id'],
    //                 'mda_id' => $data['mda_id'],
    //                 'voucher_date' => $data['voucher_date'],
    //                 'narration' => $data['narration'],
    //                 'payee_name' => $data['payee_name'],
    //                 'total_amount' => $totalAmount ?? 0,
    //                 'status' => 'Approved', // Directly approved for final accounts
    //                 'voucher_type' => $data['voucher_type'] ?? 'standard',
    //                 'created_by_user_id' => auth()->id(),
    //                 'bank_activity_id' => $data['bank_activity_id'] ?? null,
    //                 'is_final_accounts' => true, // Flag to identify final accounts vouchers
    //             ];

    //             if (isset($data['schedule_id']) && !empty($data['schedule_id'])) {
    //                 $voucherData['schedule_id'] = $data['schedule_id'];
    //             }

    //             $voucher = Voucher::create($voucherData);

    //             Log::info('Final accounts voucher created:', [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //             ]);

    //             // Create line items with programme code fields
    //             if (isset($data['items']) && is_array($data['items'])) {
    //                 foreach ($data['items'] as $itemData) {
    //                     $voucher->items()->create([
    //                         'description' => $itemData['description'],
    //                         'quantity' => $itemData['quantity'],
    //                         'unit_price' => $itemData['unit_price'],
    //                         'sub_total' => $itemData['sub_total'],
    //                         'economy_code_id' => $itemData['economy_code_id'] ?? null,
    //                         'economy_code_item_id' => $itemData['economy_code_item_id'] ?? null,
    //                         'programme_code_id' => $itemData['programme_code_id'] ?? null,
    //                         'programme_code' => $itemData['programme_code'] ?? null,
    //                         'programme_name' => $itemData['programme_name'] ?? null,
    //                         'budget_code' => $itemData['budget_code'] ?? null,
    //                     ]);
    //                 }
    //                 Log::info('Line items created:', ['count' => count($data['items'])]);
    //             }

    //             // Handle file uploads if provided
    //             if (!empty($files)) {
    //                 $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
    //             }

    //             // No approval workflow - directly approved
    //             // Just create a record that it was approved
    //             VoucherApproval::create([
    //                 'voucher_id' => $voucher->id,
    //                 'user_id' => auth()->id(),
    //                 'approval_role' => 'Final Accounts',
    //                 'approval_step' => 1,
    //                 'action' => 'Approved',
    //                 'status' => 'approved',
    //                 'comment' => 'Final accounts voucher - automatically approved',
    //                 'action_at' => now(),
    //                 'approved_at' => now(),
    //                 'approval_level' => 1,
    //             ]);

    //             Log::info('=== FINAL ACCOUNTS VOUCHER CREATION COMPLETED ===', [
    //                 'voucher_id' => $voucher->id,
    //                 'status' => 'Approved',
    //             ]);

    //             return $voucher->load([
    //                 'mda',
    //                 'financialYear',
    //                 'schedule',
    //                 'items',
    //                 'documents',
    //                 'creator'
    //             ]);
    //         } catch (\Exception $e) {
    //             Log::error('Final Accounts Voucher Creation Failed: ' . $e->getMessage(), [
    //                 'trace' => $e->getTraceAsString()
    //             ]);
    //             throw $e;
    //         }
    //     });
    // }
    /**
     * Create a final accounts voucher (direct approval, no workflow)
     */
    public function createFinalAccountsVoucher(array $data, array $files = [], array $documentTypes = [])
    {
        return DB::transaction(function () use ($data, $files, $documentTypes) {
            try {
                Log::info('=== FINAL ACCOUNTS VOUCHER CREATION STARTED ===', [
                    'voucher_number' => $data['voucher_number'],
                    'voucher_type' => $data['voucher_type'] ?? 'standard',
                ]);

                // Calculate total amount from items
                $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

                // Create the main voucher with Approved status
                $voucherData = [
                    'voucher_number' => strtoupper($data['voucher_number']),
                    'year_id' => $data['year_id'],
                    'mda_id' => $data['mda_id'],
                    'voucher_date' => $data['voucher_date'],
                    'narration' => $data['narration'],
                    'payee_name' => $data['payee_name'],
                    'total_amount' => $totalAmount ?? 0,
                    'status' => 'Approved',
                    'voucher_type' => $data['voucher_type'] ?? 'standard',
                    'created_by_user_id' => auth()->id(),
                    'bank_activity_id' => $data['bank_activity_id'] ?? null,
                    'is_final_accounts' => true,
                ];

                // Only add schedule_id if provided and not null
                if (isset($data['schedule_id']) && !empty($data['schedule_id'])) {
                    $voucherData['schedule_id'] = $data['schedule_id'];
                }

                $voucher = Voucher::create($voucherData);

                Log::info('Final accounts voucher created:', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'schedule_id' => $voucher->schedule_id ?? 'none',
                ]);

                // Create line items
                if (isset($data['items']) && is_array($data['items'])) {
                    foreach ($data['items'] as $itemData) {
                        $voucher->items()->create([
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'sub_total' => $itemData['sub_total'],
                            'economy_code_id' => $itemData['economy_code_id'] ?? null,
                            'economy_code_item_id' => $itemData['economy_code_item_id'] ?? null,
                            'programme_code_id' => $itemData['programme_code_id'] ?? null,
                            'programme_code' => $itemData['programme_code'] ?? null,
                            'programme_name' => $itemData['programme_name'] ?? null,
                            'budget_code' => $itemData['budget_code'] ?? null,
                        ]);
                    }
                    Log::info('Line items created:', ['count' => count($data['items'])]);
                }

                // Handle file uploads if provided
                if (!empty($files)) {
                    $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
                }

                // Create approval record (already approved)
                VoucherApproval::create([
                    'voucher_id' => $voucher->id,
                    'user_id' => auth()->id(),
                    'approval_role' => 'Final Accounts',
                    'approval_step' => 1,
                    'action' => 'Approved',
                    'status' => 'approved',
                    'comment' => 'Final accounts voucher - automatically approved',
                    'action_at' => now(),
                    'approved_at' => now(),
                    'approval_level' => 1,
                ]);

                Log::info('=== FINAL ACCOUNTS VOUCHER CREATION COMPLETED ===', [
                    'voucher_id' => $voucher->id,
                    'status' => 'Approved',
                ]);

                return $voucher->load([
                    'mda',
                    'financialYear',
                    'schedule',
                    'items',
                    'documents',
                    'creator'
                ]);
            } catch (\Exception $e) {
                Log::error('Final Accounts Voucher Creation Failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }
}