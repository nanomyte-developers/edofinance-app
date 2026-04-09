<?php

namespace App\Services;

use App\Models\Mda;
use App\Models\Schedule;
use App\Models\ScheduleItem; // Assumed model name
use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    /**
     * Create a new schedule with line items
     */
    // public function createSchedule(array $data)
    // {
    //     return DB::transaction(function () use ($data) {
    //         try {
    //             // 1. Generate Schedule Number (Format: SCH. NO.{MDA}/{Serial}/{Year})
    //             // If one isn't provided or to ensure uniqueness
    //             $scheduleNumber = $this->generateScheduleNumber($data['mda_id'], $data['year_id']);

    //             Log::info('=== SCHEDULE CREATION STARTED ===', [
    //                 'schedule_number' => $scheduleNumber,
    //                 'items_count' => count($data['items'] ?? []),
    //             ]);

    //             // 2. Calculate total amount from items
    //             $totalAmount = $this->calculateTotalAmount($data['items'] ?? []);

    //             // 3. Create the main schedule record
    //             $schedule = Schedule::create([
    //                 'schedule_number' => $scheduleNumber,
    //                 'year_id' => $data['year_id'],
    //                 'mda_id' => $data['mda_id'],
    //                 'budget_code_id' => $data['budget_code_id'], // Matches "CODE: 0233..."
    //                 'schedule_date' => now(), // Or specific date if passed
    //                 'total_amount' => $totalAmount,
    //                 'status' => $data['status'] ?? 'Draft',
    //                 'created_by_user_id' => auth()->id(),
    //             ]);

    //             Log::info('Schedule created:', ['schedule_id' => $schedule->id]);

    //             // 4. Create line items
    //             if (isset($data['items']) && is_array($data['items'])) {
    //                 $this->createLineItems($schedule, $data['items']);
    //                 Log::info('Schedule items created:', ['count' => count($data['items'])]);
    //             }

    //             Log::info('=== SCHEDULE CREATION COMPLETED ===', [
    //                 'schedule_id' => $schedule->id
    //             ]);

    //             return $schedule->load(['mda', 'items']);

    //         } catch (\Exception $e) {
    //             Log::error('ScheduleService Transaction Failed: ' . $e->getMessage(), [
    //                 'data' => $data,
    //             ]);
    //             throw $e;
    //         }
    //     });
    // }

    /**
     * The createSchedule method that was being called in store().
     * Ensure you have this in your service as well.
     */
    // public function createSchedule(array $data)
    // {
    //     return \DB::transaction(function () use ($data) {
            
    //         // 1. Generate the final schedule number based on selected MDA
    //         // This is where you would ideally re-calculate the number using $data['mda_id']
    //         // For simplicity, we just use the default number for the first save.
    //         $scheduleNumber = $data['schedule_number'] ?? $this->generateNextScheduleNumber(); 
            
    //         // 2. Create the Schedule header
    //         $schedule = \App\Models\Schedule::create([
    //             'schedule_number' => $scheduleNumber,
    //             'year_id' => $data['year_id'],
    //             'mda_id' => $data['mda_id'],
    //             'budget_code_id' => $data['budget_code_id'],
    //             'total_amount' => $data['total_amount'],
    //             'status' => $data['status'],
    //         ]);

    //         // 3. Save line items
    //         $scheduleItems = collect($data['items'])->map(function ($item) use ($schedule) {
    //             return new \App\Models\ScheduleItem([
    //                 'schedule_id' => $schedule->id,
    //                 'payment_date' => $item['date'],
    //                 'serial_number' => $item['serial_no'],
    //                 'economy_head_id' => $item['economy_head_id'], // Use the ID
    //                 'payee_name' => $item['payee_name'],
    //                 'amount' => $item['amount'],
    //             ]);
    //         });

    //         $schedule->items()->saveMany($scheduleItems);
            
    //         return $schedule;
    //     });
    // }

    // public function createSchedule(array $data)
    // {
    //     return DB::transaction(function () use ($data) {
            
    //         // 1. Generate the FINAL, correct schedule number
    //         $scheduleNumber = $this->generateNextScheduleNumber($data['mda_id'], $data['year_id']); 
            
    //         // 2. Create the Schedule header
    //         $schedule = Schedule::create([
    //             'schedule_number' => $scheduleNumber,
    //             'year_id' => $data['year_id'],
    //             'mda_id' => $data['mda_id'],
    //             'budget_code_id' => $data['budget_code_id'],
    //             'total_amount' => $data['total_amount'],
    //             'status' => $data['status'],
    //         ]);

    //         // 3. Save line items (Ensuring the first line item has the generated serial no)
    //         $scheduleItems = collect($data['items'])->map(function ($item, $index) use ($scheduleNumber, $schedule) {
                
    //             // Extract the serial number (XXX) from the schedule number (SCH/MDA/XXX/YEAR)
    //             $scheduleParts = explode('/', $scheduleNumber);
    //             $serialNumberPart = count($scheduleParts) === 4 ? $scheduleParts[2] : $index + 1;

    //             // The user's line item serial_no is likely meant to be the same, 
    //             // but we can ensure consistency here if needed.
    //             // For now, we will use the user-provided item.serial_no but if you need to enforce 
    //             // that the first line item serial is the schedule serial, you can:
    //             /* if ($index === 0) {
    //                 $item['serial_no'] = $serialNumberPart; 
    //             }
    //             */

    //             return new \App\Models\ScheduleItem([
    //                 'schedule_id' => $schedule->id,
    //                 'payment_date' => $item['date'],
    //                 'serial_number' => $item['serial_no'], // Use user input or override as above
    //                 'economy_head_id' => $item['economy_head_id'],
    //                 'payee_name' => $item['payee_name'],
    //                 'amount' => $item['amount'],
    //             ]);
    //         });

    //         $schedule->items()->saveMany($scheduleItems);
            
    //         return $schedule;
    //     });
    // }

    // public function createSchedule(array $data)
    // {
    //     return DB::transaction(function () use ($data) {
    //         // Generate final number just before saving to be safe
    //         $numberData = $this->generateNextScheduleNumber($data['mda_id'], $data['year_id']);
            
    //         $schedule = Schedule::create([
    //             'schedule_number' => $numberData['schedule_number'],
    //             'year_id' => $data['year_id'],
    //             'mda_id' => $data['mda_id'],
    //             'budget_code_id' => $data['budget_code_id'],
    //             'total_amount' => $data['total_amount'],
    //             'status' => $data['status'],
    //         ]);

    //         // Save items
    //         foreach ($data['items'] as $item) {
    //             $schedule->items()->create([
    //                 'payment_date' => $item['date'],
    //                 'serial_number' => $item['serial_no'],
    //                 'economy_head_id' => $item['economy_head_id'],
    //                 'payee_name' => $item['payee_name'],
    //                 'amount' => $item['amount'],
    //             ]);
    //         }
            
    //         return $schedule;
    //     });
    // }

    public function createSchedule(array $data)
    {
        // dd($data);
        return DB::transaction(function () use ($data) {
            // Generate final number just before saving to be safe
            $numberData = $this->generateNextScheduleNumber($data['mda_id'], $data['year_id']);
            
            $schedule = Schedule::create([
                'schedule_number' => $numberData['schedule_number'],
                'year_id' => $data['year_id'],
                'mda_id' => $data['mda_id'],
                'budget_code_id' => $data['budget_code_id'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'schedule_date' => now(), // or use a specific date if provided
                'created_by_user_id' => auth()->id(),
            ]);

            // Save items with correct field names
            foreach ($data['items'] as $item) {
                $schedule->items()->create([
                    'item_date' => $item['date'],
                    'serial_number' => $item['serial_no'],
                    'economy_code_id' => $item['economy_code_id'],
                    'economy_code_item_id' => $item['economy_code_item_id'],
                    'payee_name' => $item['payee_name'],
                    'amount' => $item['amount'],
                ]);
            }
            
            return $schedule->load(['items.economyCode', 'items.economyCodeItem']);
        });
    }


    /**
     * Update an existing schedule
     */
    public function updateSchedule(Schedule $schedule, array $data)
    {
        return DB::transaction(function () use ($schedule, $data) {
            // Update main details
            $schedule->update([
                'year_id' => $data['year_id'] ?? $schedule->year_id,
                'mda_id' => $data['mda_id'] ?? $schedule->mda_id,
                'budget_code_id' => $data['budget_code_id'] ?? $schedule->budget_code_id,
                'status' => $data['status'] ?? $schedule->status,
            ]);

            // Re-create items if provided
            if (isset($data['items'])) {
                $this->updateLineItems($schedule, $data['items']);
            }

            // Recalculate total
            $this->recalculateTotalAmount($schedule);

            return $schedule->fresh(['mda', 'items']);
        });
    }

    /**
     * Generate Schedule Number: SCH. NO.{MDA}/{Serial}/{Year}
     * Example: SCH. NO.M.M.E/15/2025
     */
    public function generateScheduleNumber($mdaId, $yearId): string
    {
        try {
            $mda = Mda::find($mdaId);
            $year = FinancialYear::find($yearId);

            // Get MDA Initials (e.g., Ministry of Mining and Energy -> M.M.E)
            // Assuming 'initials' column exists, otherwise generate from name
            $mdaCode = $mda ? ($mda->initials ?? $this->generateInitials($mda->name)) : 'GEN';
            $yearCode = $year ? $year->name : date('Y'); // e.g., 2025

            // Find last schedule number for this MDA in this Year to increment serial
            $lastSchedule = Schedule::where('mda_id', $mdaId)
                ->where('year_id', $yearId)
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            // Regex to extract the serial number (assuming format ends in /Number/Year)
            if ($lastSchedule && preg_match('/\/(\d+)\/' . $yearCode . '$/', $lastSchedule->schedule_number, $matches)) {
                $sequence = (int)$matches[1] + 1;
            }

            // Format: SCH. NO.M.M.E/15/2025
            return "SCH. NO.{$mdaCode}/{$sequence}/{$yearCode}";

        } catch (\Exception $e) {
            Log::error('Error generating schedule number: ' . $e->getMessage());
            return 'SCH-' . uniqid(); // Fallback
        }
    }

    /**
     * Helper to generate initials if not in DB
     */
    protected function generateInitials($string) {
        $words = explode(" ", $string);
        $acronym = "";
        foreach ($words as $w) {
            $acronym .= $w[0] . ".";
        }
        return strtoupper(rtrim($acronym, '.'));
    }

    /**
     * Create line items
     */
    protected function createLineItems(Schedule $schedule, array $items): void
    {
        foreach ($items as $item) {
            ScheduleItem::create([
                'schedule_id' => $schedule->id,
                'item_date' => $item['date'], // Mapped from Vue 'date'
                'serial_number' => $item['serial_no'], // Mapped from Vue 'serial_no' (e.g., "15")
                'economy_code_id' => $item['economy_code_id'], // Mapped from Vue 'economy_head' (e.g., "22020301")
                'economy_code_item_id' => $item['economy_code_item_id'], // Mapped from Vue 'economy_head' (e.g., "22020301")
                'payee_name' => $item['payee_name'],
                'amount' => $item['amount'],
            ]);
        }
    }

    /**
     * Update line items (Delete & Re-create)
     */
    protected function updateLineItems(Schedule $schedule, array $items): void
    {
        $schedule->items()->delete();
        $this->createLineItems($schedule, $items);
    }

    /**
     * Calculate total from array
     */
    protected function calculateTotalAmount(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            if (isset($item['amount']) && is_numeric($item['amount'])) {
                $total += (float) $item['amount'];
            }
        }
        return round($total, 2);
    }

    /**
     * Recalculate total from DB relation
     */
    protected function recalculateTotalAmount(Schedule $schedule): void
    {
        $total = $schedule->items()->sum('amount');
        $schedule->update(['total_amount' => $total]);
    }

    /**
     * Delete schedule
     */
    public function deleteSchedule(Schedule $schedule): bool
    {
        return DB::transaction(function () use ($schedule) {
            $schedule->items()->delete();
            return $schedule->delete();
        });
    }

    /**
     * Generates the next schedule number.
     * Format Example: SCH/MDA_INIT/001/2025
     */
    // public function generateNextScheduleNumber(): string
    // {
    //     // Fetch the current year's ID or use the current year number
    //     $currentYear = date('Y');
        
    //     // Find the latest schedule number created this year
    //     $latestSchedule = \App\Models\Schedule::where('schedule_number', 'like', "SCH/%/{$currentYear}")
    //         ->latest('id')
    //         ->first();

    //     $nextNumber = 1;

    //     if ($latestSchedule) {
    //         // Example: Extract '001' from 'SCH/MME/001/2025'
    //         $parts = explode('/', $latestSchedule->schedule_number);
    //         if (count($parts) === 4) {
    //             $lastNumber = (int)$parts[2];
    //             $nextNumber = $lastNumber + 1;
    //         }
    //     }
        
    //     // Use a placeholder initials (e.g., 'GEN' for General) until an MDA is selected.
    //     $paddedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    //     return "SCH/GEN/{$paddedNumber}/{$currentYear}";
    // }

    /**
     * Generates the next schedule number based on MDA and Financial Year.
     * Format: SCH/MDA_INITIALS/XXX/YEAR_NAME (e.g., SCH/MME/001/2025)
     */
    // public function generateNextScheduleNumber(int $mdaId = null, int $yearId = null): string
    // {
    //     // 1. Determine MDA Initials (Use 'GEN' as fallback for placeholder)
    //     $mdaInitials = 'GEN';
    //     if ($mdaId) {
    //         // Assuming Mda model is linked to administrative_codes or has initials field
    //         $mda = DB::table('administrative_codes')->where('id', $mdaId)->first();
            
    //         // Assuming the first three letters of the name or a dedicated 'initials' column
    //         // We'll use the first 3 characters of the name as a placeholder for initials
    //         $mdaInitials = $mda ? strtoupper(substr($mda->name, 0, 3)) : 'UNK';
    //     }
        
    //     // 2. Determine Year Name (Use current year as fallback)
    //     $yearName = date('Y');
    //     if ($yearId) {
    //          $financialYear = FinancialYear::find($yearId);
    //          $yearName = $financialYear ? $financialYear->name : date('Y');
    //     }

    //     // 3. Find the latest schedule number for this MDA and Year
    //     $latestSchedule = Schedule::where('mda_id', $mdaId)
    //         ->where('year_id', $yearId)
    //         ->latest('id')
    //         ->first();

    //     $nextNumber = 1;

    //     if ($latestSchedule) {
    //         // Extract the serial number from the existing format (e.g., '001' from 'SCH/MME/001/2025')
    //         $parts = explode('/', $latestSchedule->schedule_number);
    //         // Check if the format matches
    //         if (count($parts) === 4 && is_numeric($parts[2])) {
    //             $lastNumber = (int)$parts[2];
    //             $nextNumber = $lastNumber + 1;
    //         }
    //     }
        
    //     $paddedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    //     return "SCH/{$mdaInitials}/{$paddedNumber}/{$yearName}";
    // }

    // public function generateNextScheduleNumber(int $mdaId = null, int $yearId = null): string
    // {
    //     // 1. Determine MDA Initials (Use 'GEN' as fallback for placeholder)
    //     $mdaInitials = 'GEN';
        
    //     if ($mdaId) {
    //         // --- FIX APPLIED HERE: Fetching 'initials' from 'administrative_sector_codes' ---
    //         $mda = DB::table('administrative_sector_codes')
    //             ->where('id', $mdaId)
    //             ->select('initials', 'name') // Select 'name' as fallback/check
    //             ->first();
            
    //         if ($mda && $mda->initials) {
    //             // Use the value from the 'initials' column
    //             $mdaInitials = strtoupper($mda->initials); 
    //         } else if ($mda && $mda->name) {
    //             // Fallback: If 'initials' is null, use the old placeholder logic 
    //             // until the seeder/script runs correctly.
    //             $mdaInitials = strtoupper(substr($mda->name, 0, 3));
    //         } else {
    //             $mdaInitials = 'UNK'; // Unknown MDA
    //         }
    //     }
        
    //     // 2. Determine Year Name (Use current year as fallback)
    //     $yearName = date('Y');
    //     if ($yearId) {
    //         // You might need to adjust the model name if FinancialYear is not the correct model
    //         $financialYear = FinancialYear::find($yearId); 
    //         $yearName = $financialYear ? $financialYear->name : date('Y');
    //     }

    //     // 3. Find the latest schedule number for this MDA and Year
    //     $latestSchedule = Schedule::where('mda_id', $mdaId)
    //         ->where('year_id', $yearId)
    //         ->latest('id')
    //         ->first();

    //     $nextNumber = 1;

    //     if ($latestSchedule) {
    //         // Extract the serial number from the existing format (e.g., '001' from 'SCH/MME/001/2025')
    //         $parts = explode('/', $latestSchedule->schedule_number);
    //         // Check if the format matches
    //         if (count($parts) === 4 && is_numeric($parts[2])) {
    //             $lastNumber = (int)$parts[2];
    //             $nextNumber = $lastNumber + 1;
    //         }
    //     }
        
    //     $paddedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    //     return "SCH/{$mdaInitials}/{$paddedNumber}/{$yearName}";
    // }

    // public function generateNextScheduleNumber(int $mdaId, int $yearId): array
    // {
    //     // 1. Get MDA Initials (e.g., "MWR")
    //     $mda = DB::table('administrative_sector_codes')->where('id', $mdaId)->first();
    //     $initials = $mda ? ($mda->initials ?? strtoupper(substr($mda->name, 0, 3))) : 'GEN';

    //     // 2. Get Year (e.g., "2025")
    //     $year = \App\Models\FinancialYear::find($yearId);
    //     $yearName = $year ? $year->name : date('Y');

    //     // 3. Query DB for the LAST schedule specifically for this MDA and Year
    //     $lastSchedule = \App\Models\Schedule::where('mda_id', $mdaId)
    //         ->where('year_id', $yearId)
    //         ->latest('id')
    //         ->first();

    //     // 4. Determine next serial
    //     $nextSerial = 1;
    //     if ($lastSchedule) {
    //         // Try to parse "SCH/MWR/15/2025" -> get "15"
    //         $parts = explode('/', $lastSchedule->schedule_number);
    //         if (count($parts) >= 3 && is_numeric($parts[2])) {
    //             $nextSerial = (int)$parts[2] + 1;
    //         }
    //     }

    //     // 5. Format
    //     // You requested "0" in your prompt "SCH/MWR/0/2025", but usually, it's padded like "001"
    //     // I will use standard padding (e.g., 15) to match your previous image.
    //     return [
    //         'schedule_number' => sprintf("SCH/%s/%s/%s", $initials, $nextSerial, $yearName),
    //         'serial_no' => (string)$nextSerial // This goes to the line item
    //     ];
    // }

    /**
 * Generates the next schedule number and serial based on MDA and Year.
 * Returns array: ['schedule_number' => '...', 'serial_no' => '...']
 */
public function generateNextScheduleNumber(int $mdaId, int $yearId): array
{
    // 1. Determine MDA Initials
    // Fetch from the administrative table
    // $mda = DB::table('administrative_sector_codes')->where('administrative_code_id', $mdaId)->first();

    // dd($mdaId);
    $mda = Mda::whereId( $mdaId)->first();

    // dd($mda);
    
    
    $mdaInitials = 'GEN'; // Default to GEN

    if ($mda) {
        // Use existing initials if they are not empty (null, empty string, or 0).
        // Fallback to deriving initials from the name if the initials column is empty.
        $mdaInitials = $mda->initials 
            ? $mda->initials 
            : $this->deriveInitials($mda->name);
    }

    // dd($mda);
    
    // Ensure initials are uppercase
    $mdaInitials = strtoupper($mdaInitials);
// dd($mdaInitials);
    // 2. Determine Year Name (e.g., "2025")
    $year = FinancialYear::find($yearId);
    $yearName = $year ? $year->name : date('Y');

    // 3. Find the latest schedule for this specific MDA and Year
    // FIX: Must filter by BOTH mda_id AND the year in the schedule_number.
    $lastSchedule = Schedule::where('mda_id', $mdaId)
        ->where('schedule_number', 'LIKE', "%/{$yearName}") // Matches ends with /2025
        ->latest('id')
        ->first();

    // 4. Calculate Next Serial
    $nextSerial = 1; // Default if no schedule exists for this ministry
    
    if ($lastSchedule) {
        // Parse format: SCH/MME/15/2025
        $parts = explode('/', $lastSchedule->schedule_number);
        
        // The serial is usually the 3rd part (index 2)
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $nextSerial = (int)$parts[2] + 1;
        }
    }


    // 5. Format the number
    // Format: SCH/{INITIALS}/{SERIAL}/{YEAR}
    $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";

    while (Schedule::where('schedule_number', $scheduleNumber)->exists()) {
        $nextSerial++;
        $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";
    }

    return [
        'schedule_number' => $scheduleNumber,
        'serial_no' => (string)$nextSerial // This will populate the line item
    ];
}

/**
 * Helper to generate initials if column is empty.
 * Uses the first letter of every significant word, truncated to 3 characters.
 */
private function deriveInitials($name) {
    // Words to ignore
    $stopwords = ['of', 'and', 'the', '&', 'for']; 
    
    // Split the name by space and hyphen
    $words = preg_split('/[ -]/', $name, -1, PREG_SPLIT_NO_EMPTY);
    
    $acronym = "";
    foreach ($words as $w) {
        // Check if the word is not a stop word (case-insensitive)
        if (!empty($w) && !in_array(strtolower($w), $stopwords)) {
            $acronym .= $w[0];
        }
    }
    // Truncate to a maximum of 3 characters
    return substr($acronym, 0, 3);
}
}