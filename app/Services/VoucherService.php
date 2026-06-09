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
    // public function createVoucher(array $data, array $files = [], array $documentTypes = [])
    // {
    //     return DB::transaction(function () use ($data, $files, $documentTypes) {
    //         try {
    //             // Generate voucher number
    //             $voucherNumber = $this->generateVoucherNumber($data['year_id']);

    //             Log::info('=== VOUCHER CREATION STARTED ===', [
    //                 'voucher_number' => $voucherNumber,
    //                 'files_count' => count($files),
    //                 'document_types_count' => count($documentTypes),
    //                 'document_types_received' => $documentTypes,
    //             ]);

    //             // Calculate total amount from items
    //             $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

    //             // Create the main voucher
    //             $voucher = Voucher::create([
    //                 'voucher_number' => $voucherNumber,
    //                 'year_id' => $data['year_id'],
    //                 'mda_id' => $data['mda_id'],
    //                 'voucher_date' => $data['voucher_date'],
    //                 'narration' => $data['narration'],
    //                 'total_amount' => $totalAmount ?? 0,
    //                 'status' => $data['status'] ?? 'Draft',
    //                 'voucher_type' => $data['voucher_type'] ?? 'standard',
    //                 'created_by_user_id' => auth()->id(),
    //             ]);

    //             Log::info('Voucher created:', ['voucher_id' => $voucher->id]);

    //             // Create line items if provided
    //             if (isset($data['items']) && is_array($data['items'])) {
    //                 $this->createLineItems($voucher, $data['items']);
    //                 Log::info('Line items created:', ['count' => count($data['items'])]);
    //             }

    //             // Handle file uploads if provided - USE THE NEW METHOD
    //             if (!empty($files)) {
    //                 $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
    //             }

    //             // Create initial approval record
    //             $this->createInitialApproval($voucher);

    //             // Reload voucher with relationships to see final state
    //             $voucher->load('documents');

    //             Log::info('=== VOUCHER CREATION COMPLETED ===', [
    //                 'voucher_id' => $voucher->id,
    //                 'documents_created' => $voucher->documents->count(),
    //                 'document_types_final' => $voucher->documents->pluck('document_type', 'file_name')->toArray()
    //             ]);

    //             return $voucher->load([
    //                 'mda',
    //                 'financialYear',
    //                 'items',
    //                 'documents',
    //                 'approvals.user',
    //                 'creator'
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('VoucherService Transaction Failed: ' . $e->getMessage(), [
    //                 'data' => $data,
    //                 'files_count' => count($files),
    //                 'document_types_count' => count($documentTypes),
    //             ]);
    //             throw $e;
    //         }
    //     });
    // }

    // public function createVoucher(array $data, array $files = [], array $documentTypes = [])
    // {
    //     return DB::transaction(function () use ($data, $files, $documentTypes) {
    //         try {
    //             // Generate voucher number
    //             $voucherNumber = $this->generateVoucherNumber($data['year_id']);

    //             Log::info('=== VOUCHER CREATION STARTED ===', [
    //                 'voucher_number' => $voucherNumber,
    //                 'schedule_id' => $data['schedule_id'] ?? null,
    //                 'voucher_type' => $data['voucher_type'] ?? 'standard',
    //                 'files_count' => count($files),
    //                 'document_types_count' => count($documentTypes),
    //             ]);

    //             // Calculate total amount from items
    //             $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

    //             // Determine if retirement is required
    //             $requiresRetirement = ($data['voucher_type'] ?? 'standard') === 'prepayment';

    //             // Create the main voucher with schedule_id and retirement flag
    //             $voucherData = [
    //                 'voucher_number' => $voucherNumber,
    //                 'year_id' => $data['year_id'],
    //                 'mda_id' => $data['mda_id'],
    //                 'voucher_date' => $data['voucher_date'],
    //                 'narration' => $data['narration'],
    //                 'total_amount' => $totalAmount ?? 0,
    //                 'status' => $data['status'] ?? 'Draft',
    //                 'voucher_type' => $data['voucher_type'] ?? 'standard',
    //                 'created_by_user_id' => auth()->id(),
    //                 'requires_retirement' => $requiresRetirement,
    //             ];

    //             // Add schedule_id if provided
    //             if (isset($data['schedule_id']) && !empty($data['schedule_id'])) {
    //                 $voucherData['schedule_id'] = $data['schedule_id'];
    //             }

    //             $voucher = Voucher::create($voucherData);

    //             Log::info('Voucher created:', [
    //                 'voucher_id' => $voucher->id,
    //                 'schedule_id' => $voucher->schedule_id,
    //                 'requires_retirement' => $voucher->requires_retirement,
    //             ]);

    //             // Create line items if provided
    //             if (isset($data['items']) && is_array($data['items'])) {
    //                 $this->createLineItems($voucher, $data['items']);
    //                 Log::info('Line items created:', ['count' => count($data['items'])]);
    //             }

    //             // Handle file uploads if provided
    //             if (!empty($files)) {
    //                 $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
    //             }

    //             // Create initial approval record
    //             $this->createInitialApproval($voucher);

    //             // Reload voucher with relationships to see final state
    //             $voucher->load('documents');

    //             Log::info('=== VOUCHER CREATION COMPLETED ===', [
    //                 'voucher_id' => $voucher->id,
    //                 'schedule_id' => $voucher->schedule_id,
    //                 'requires_retirement' => $voucher->requires_retirement,
    //                 'documents_created' => $voucher->documents->count(),
    //             ]);

    //             return $voucher->load([
    //                 'mda',
    //                 'financialYear',
    //                 'schedule', // Load schedule relationship
    //                 'items',
    //                 'documents',
    //                 'approvals.user',
    //                 'creator'
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('VoucherService Transaction Failed: ' . $e->getMessage(), [
    //                 'data' => $data,
    //                 'schedule_id' => $data['schedule_id'] ?? null,
    //             ]);
    //             throw $e;
    //         }
    //     });
    // }

    public function createVoucher(array $data, array $files = [], array $documentTypes = [])
    {
        return DB::transaction(function () use ($data, $files, $documentTypes) {
            try {

                // Generate voucher number
                // $voucherNumber = $this->generateVoucherNumber($data['year_id']);

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
                // The retirement flag will be set automatically in the model boot method
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

                // dd($voucherData);
                $voucher = Voucher::create($voucherData);
                // dd($voucher);
                Log::info('Voucher created:', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'schedule_id' => $voucher->schedule_id,
                    'voucher_type' => $voucher->voucher_type,
                    'requires_retirement' => $voucher->requires_retirement,
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

                // Reload voucher with relationships to see final state
                $voucher->load('documents');

                Log::info('=== VOUCHER CREATION COMPLETED ===', [
                    'voucher_id' => $voucher->id,
                    'schedule_id' => $voucher->schedule_id,
                    'voucher_type' => $voucher->voucher_type,
                    'requires_retirement' => $voucher->requires_retirement,
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
     * NEW METHOD: Handle document uploads with guaranteed type assignment
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
     * Update an existing voucher
     */
    // public function updateVoucher(Voucher $voucher, array $data, array $files = [], array $documentTypes = [])
    // {
    //     return DB::transaction(function () use ($voucher, $data, $files, $documentTypes) {
    //         // Update main voucher data
    //         $voucher->update([
    //             'voucher_date' => $data['voucher_date'] ?? $voucher->voucher_date,
    //             'narration' => $data['narration'] ?? $voucher->narration,
    //             'year_id' => $data['year_id'] ?? $voucher->year_id,
    //             'mda_id' => $data['mda_id'] ?? $voucher->mda_id,
    //             'status' => $data['status'] ?? $voucher->status,
    //         ]);

    //         // Update line items if provided
    //         if (isset($data['items'])) {
    //             $this->updateLineItems($voucher, $data['items']);
    //         }

    //         // Handle new file uploads if provided - USE THE NEW METHOD
    //         if (!empty($files)) {
    //             $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
    //         }

    //         // Handle document deletions
    //         if (isset($data['documents_to_delete']) && is_array($data['documents_to_delete'])) {
    //             $this->handleDocumentDeletions($voucher, $data['documents_to_delete']);
    //         }

    //         // Recalculate total amount
    //         $this->recalculateTotalAmount($voucher);

    //         return $voucher->fresh(['mda', 'items', 'documents']);
    //     });
    // }

    // public function updateVoucher(Voucher $voucher, array $data, array $files = [], array $documentTypes = [])
    // {
    //     return DB::transaction(function () use ($voucher, $data, $files, $documentTypes) {
    //         // Prepare update data
    //         $updateData = [
    //             'voucher_date' => $data['voucher_date'] ?? $voucher->voucher_date,
    //             'voucher_number' => strtoupper($data['voucher_number']) ?? $voucher->voucher_number,
    //             'voucher_type' => $data['voucher_type'] ?? $voucher->voucher_type,
    //             'narration' => $data['narration'] ?? $voucher->narration,
    //             'year_id' => $data['year_id'] ?? $voucher->year_id,
    //             'mda_id' => $data['mda_id'] ?? $voucher->mda_id,
    //             'status' => $data['status'] ?? $voucher->status,
    //             'payee_name' => $data['payee_name'] ?? $voucher->payee_name,
    //             'bank_activity_id' => $data['bank_activity_id'] ?? null,
    //         ];

    //         // dd($updateData);

    //         // Update schedule_id if provided
    //         if (isset($data['schedule_id'])) {
    //             $updateData['schedule_id'] = $data['schedule_id'];
    //         }

    //         // Update main voucher data
    //         $voucher->update($updateData);

    //         // Update line items if provided
    //         if (isset($data['items'])) {
    //             $this->updateLineItems($voucher, $data['items']);
    //         }

    //         // Handle new file uploads if provided
    //         if (!empty($files)) {
    //             $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
    //         }

    //         // Handle document deletions
    //         if (isset($data['documents_to_delete']) && is_array($data['documents_to_delete'])) {
    //             $this->handleDocumentDeletions($voucher, $data['documents_to_delete']);
    //         }

    //         // Recalculate total amount
    //         $this->recalculateTotalAmount($voucher);

    //         return $voucher->fresh(['mda', 'schedule', 'items', 'documents']);
    //     });
    // }
    public function updateVoucher(Voucher $voucher, array $data, array $files = [], array $documentTypes = [])
    {
        DB::beginTransaction();

        try {

            $data = $this->checkRetirementStatus($voucher, $data);

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

            // Sync items
            $voucher->items()->delete();
            foreach ($data['items'] as $itemData) {
                $voucher->items()->create([
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'sub_total' => $itemData['sub_total'],
                    'economy_code_id' => $itemData['economy_code_id'] ?? null,
                    'economy_code_item_id' => $itemData['economy_code_item_id'] ?? null,
                ]);
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
            return $voucher->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
     * Generate unique voucher number
     */
    protected function generateVoucherNumber($yearId): string
    {
        $prefix = 'VCH';

        try {
            $year = \App\Models\FinancialYear::find($yearId);
            $yearCode = $year ? substr($year->name, 2, 2) : date('y');
        } catch (\Exception $e) {
            $yearCode = date('y');
        }

        // Get the last voucher number for this year
        $lastVoucher = Voucher::where('year_id', $yearId)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastVoucher && preg_match('/-(\d+)$/', $lastVoucher->voucher_number, $matches)) {
            $sequence = (int)$matches[1] + 1;
        }

        return "{$prefix}-{$yearCode}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create line items for voucher
     */
    // protected function createLineItems(Voucher $voucher, array $items): void
    // {
    //     foreach ($items as $item) {
    //         VoucherItem::create([
    //             'voucher_id' => $voucher->id,
    //             'description' => $item['description'],
    //             'economy_code_id' => $itemData['economy_code_id'] ?? null, // ADDED
    //             'economy_code_item_id' => $itemData['economy_code_item_id'] ?? null, // ADDED
    //             'quantity' => $item['quantity'],
    //             'unit_price' => $item['unit_price'],
    //             'sub_total' => $item['sub_total'],
    //             'budget_code' => $item['budget_code'] ?? '0',
    //         ]);
    //     }
    // }
    // protected function createLineItems(Voucher $voucher, array $items): void
    // {
    //     foreach ($items as $item) {
    //         VoucherItem::create([
    //             'voucher_id' => $voucher->id,
    //             'description' => $item['description'],
    //             'economy_code_id' => $item['economy_code_id'] ?? null, // FIXED: was $itemData
    //             'economy_code_item_id' => $item['economy_code_item_id'] ?? null, // FIXED: was $itemData
    //             'quantity' => $item['quantity'],
    //             'unit_price' => $item['unit_price'],
    //             'sub_total' => $item['sub_total'],
    //             'budget_code' => $item['budget_code'] ?? '0',
    //         ]);
    //     }
    // }

    protected function createLineItems(Voucher $voucher, array $items): void
    {
        foreach ($items as $item) {
            VoucherItem::create([
                'voucher_id' => $voucher->id,
                'description' => $item['description'],
                'economy_code_id' => $item['economy_code_id'] ?? null, // FIXED: was $itemData
                'economy_code_item_id' => $item['economy_code_item_id'] ?? null, // FIXED: was $itemData
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total'],
                'budget_code' => $item['budget_code'] ?? '0',
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
     * OLD METHOD - Keep for reference but use new method above
     */
    protected function handleDocumentUploads(Voucher $voucher, array $files, array $documentTypes = []): void
    {
        // Use the new method instead
        $this->handleDocumentUploadsNew($voucher, $files, $documentTypes);
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
                // Use multiple matching strategies
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
     * Recalculate total amount from line items
     */
    protected function recalculateTotalAmount(Voucher $voucher): void
    {
        $totalAmount = $voucher->items()->sum('sub_total');
        $voucher->update(['total_amount' => $totalAmount]);
    }

    public function checkRetirementStatus(Voucher $voucher, array $data): array
    {
        if ($voucher->retirementVoucher != null) {
            if (strtolower($voucher->retirementVoucher->status) === 'submitted') {
                $data['status'] = 'Approved';
            }
        }
        return $data;
    }
}
