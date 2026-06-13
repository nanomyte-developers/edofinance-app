<?php
// app/Services/BudgetService.php

namespace App\Services;

use App\Models\Voucher;
use App\Models\ProgrammeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetService
{
    /**
     * Validate budget availability for a voucher
     */
    public function validateVoucherBudget(Voucher $voucher): array
    {
        $errors = [];
        $budgetValidation = [];
        
        // Group items by programme_code_id
        $itemsByProgramme = $voucher->items->groupBy('programme_code_id');
        
        foreach ($itemsByProgramme as $programmeCodeId => $items) {
            if (!$programmeCodeId) {
                continue;
            }
            
            $programmeCode = ProgrammeCode::find($programmeCodeId);
            if (!$programmeCode) {
                $errors[] = "Programme code not found for items";
                continue;
            }
            
            $totalAmount = $items->sum('sub_total');
            
            if (!$programmeCode->hasAvailableBudget($totalAmount)) {
                $errors[] = "Insufficient budget for programme '{$programmeCode->code} - {$programmeCode->name}'. " .
                            "Available: " . number_format($programmeCode->remaining_budget, 2) . 
                            ", Required: " . number_format($totalAmount, 2);
            }
            
            $budgetValidation[] = [
                'programme_code_id' => $programmeCodeId,
                'programme_code' => $programmeCode->code,
                'programme_name' => $programmeCode->name,
                'amount' => $totalAmount,
                'available_budget' => $programmeCode->remaining_budget,
            ];
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'validations' => $budgetValidation,
        ];
    }
    
    /**
     * Deduct budget when voucher is approved
     */
    public function deductBudgetForVoucher(Voucher $voucher): bool
    {
        // Only deduct if voucher is being approved and not already deducted
        if ($voucher->status !== 'Approved') {
            Log::warning('Attempted to deduct budget for non-approved voucher', [
                'voucher_id' => $voucher->id,
                'status' => $voucher->status
            ]);
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            $itemsByProgramme = $voucher->items->groupBy('programme_code_id');
            $deductions = [];
            
            foreach ($itemsByProgramme as $programmeCodeId => $items) {
                if (!$programmeCodeId) {
                    continue;
                }
                
                $programmeCode = ProgrammeCode::find($programmeCodeId);
                if (!$programmeCode) {
                    throw new \Exception("Programme code not found for ID: {$programmeCodeId}");
                }
                
                $totalAmount = $items->sum('sub_total');
                
                // Check budget availability again before deducting
                if (!$programmeCode->hasAvailableBudget($totalAmount)) {
                    throw new \Exception("Budget insufficient for programme: {$programmeCode->code}. Available: {$programmeCode->remaining_budget}, Required: {$totalAmount}");
                }
                
                // Store original values for logging
                $originalUtilized = $programmeCode->utilized_budget;
                $originalRemaining = $programmeCode->remaining_budget;
                
                // Deduct budget using the model's method
                $programmeCode->updateUtilizedBudget($totalAmount, true);
                
                $deductions[] = [
                    'programme_code' => $programmeCode->code,
                    'amount' => $totalAmount,
                    'original_utilized' => $originalUtilized,
                    'new_utilized' => $programmeCode->utilized_budget,
                    'original_remaining' => $originalRemaining,
                    'new_remaining' => $programmeCode->remaining_budget,
                ];
                
                Log::info('Budget deducted for programme', [
                    'programme_code' => $programmeCode->code,
                    'amount' => $totalAmount,
                    'remaining_budget' => $programmeCode->remaining_budget,
                    'voucher_id' => $voucher->id,
                ]);
            }
            
            DB::commit();
            
            Log::info('Budget successfully deducted for voucher', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'deductions' => $deductions,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to deduct budget for voucher', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Release budget when voucher is rejected or returned
     */
    public function releaseBudgetForVoucher(Voucher $voucher): bool
    {
        DB::beginTransaction();
        
        try {
            $itemsByProgramme = $voucher->items->groupBy('programme_code_id');
            $releases = [];
            
            foreach ($itemsByProgramme as $programmeCodeId => $items) {
                if (!$programmeCodeId) {
                    continue;
                }
                
                $programmeCode = ProgrammeCode::find($programmeCodeId);
                if (!$programmeCode) {
                    throw new \Exception("Programme code not found for ID: {$programmeCodeId}");
                }
                
                $totalAmount = $items->sum('sub_total');
                
                // Release budget using the model's method
                $programmeCode->updateUtilizedBudget($totalAmount, false);
                
                $releases[] = [
                    'programme_code' => $programmeCode->code,
                    'amount' => $totalAmount,
                    'new_remaining' => $programmeCode->remaining_budget,
                ];
                
                Log::info('Budget released for programme', [
                    'programme_code' => $programmeCode->code,
                    'amount' => $totalAmount,
                    'remaining_budget' => $programmeCode->remaining_budget,
                    'voucher_id' => $voucher->id,
                ]);
            }
            
            DB::commit();
            
            Log::info('Budget successfully released for voucher', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'releases' => $releases,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to release budget for voucher', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}