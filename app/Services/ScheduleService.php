<?php

namespace App\Services;

use App\Models\Mda;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    /**
     * Create a new schedule with line items
     */
    public function createSchedule(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Generate final number just before saving with item count
            $numberData = $this->generateNextScheduleNumber(
                $data['mda_id'], 
                $data['year_id'], 
                count($data['items'])
            );
            
            $schedule = Schedule::create([
                'schedule_number' => $numberData['schedule_number'],
                'year_id' => $data['year_id'],
                'mda_id' => $data['mda_id'],
                'budget_code_id' => $data['budget_code_id'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'schedule_date' => now(),
                'created_by_user_id' => auth()->id(),
            ]);

            // ✅ Extract the base serial and prefix from the schedule number
            $parts = explode('/', $numberData['schedule_number']);
            $baseSerial = count($parts) >= 3 ? (int)$parts[2] : 1;
            $prefix = count($parts) >= 2 ? $parts[0] . '/' . $parts[1] : 'SCH/MF';
            $year = $parts[3] ?? date('Y');

            // ✅ Save items with sequential serial numbers
            $counter = $baseSerial;
            foreach ($data['items'] as $item) {
                // Format: SCH/MF/1/2026, SCH/MF/2/2026, etc.
                $fullSerial = $prefix . '/' . $counter . '/' . $year;
                
                $schedule->items()->create([
                    'item_date' => $item['date'],
                    'serial_number' => $fullSerial,
                    'economy_code_id' => $item['economy_code_id'],
                    'economy_code_item_id' => $item['economy_code_item_id'],
                    'payee_name' => $item['payee_name'],
                    'amount' => $item['amount'],
                ]);
                $counter++;
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

            $mdaCode = $mda ? ($mda->initials ?? $this->generateInitials($mda->name)) : 'GEN';
            $yearCode = $year ? $year->name : date('Y');

            $lastSchedule = Schedule::where('mda_id', $mdaId)
                ->where('year_id', $yearId)
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($lastSchedule && preg_match('/\/(\d+)\/' . $yearCode . '$/', $lastSchedule->schedule_number, $matches)) {
                $sequence = (int)$matches[1] + 1;
            }

            return "SCH. NO.{$mdaCode}/{$sequence}/{$yearCode}";

        } catch (\Exception $e) {
            Log::error('Error generating schedule number: ' . $e->getMessage());
            return 'SCH-' . uniqid();
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
                'item_date' => $item['date'],
                'serial_number' => $item['serial_no'],
                'economy_code_id' => $item['economy_code_id'],
                'economy_code_item_id' => $item['economy_code_item_id'],
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
     * ✅ Generates the next schedule number based on TOTAL LINE ITEMS across all schedules.
     * Returns array: ['schedule_number' => '...', 'serial_no' => '...']
     * 
     * Example: If there are 6 total items across all schedules for MDA "MF" in 2026,
     *          the next schedule number will be SCH/MF/7/2026
     */
    public function generateNextScheduleNumber(int $mdaId, int $yearId, int $itemCount = 1): array
    {
        // 1. Determine MDA Initials
        $mda = Mda::whereId($mdaId)->first();
        $mdaInitials = 'GEN';

        if ($mda) {
            $mdaInitials = $mda->initials 
                ? $mda->initials 
                : $this->deriveInitials($mda->name);
        }
        $mdaInitials = strtoupper($mdaInitials);

        // 2. Determine Year Name
        $year = FinancialYear::find($yearId);
        $yearName = $year ? $year->name : date('Y');

        // 3. ✅ Get the TOTAL number of line items across ALL schedules for this MDA and Year
        // This ensures each line item gets a unique sequential number
        $totalItems = ScheduleItem::whereHas('schedule', function ($query) use ($mdaId, $yearName) {
            $query->where('mda_id', $mdaId)
                  ->where('schedule_number', 'LIKE', "%/{$yearName}");
        })->count();

        // 4. ✅ Calculate the next serial number based on total items + 1
        // This ensures the schedule number is the next available number
        $nextSerial = $totalItems + 1;

        // 5. Format the schedule number
        $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";

        // 6. ✅ Ensure uniqueness - check if this schedule number already exists
        while (Schedule::where('schedule_number', $scheduleNumber)->exists()) {
            $nextSerial++;
            $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";
        }

        Log::info('Generated schedule number:', [
            'mda_id' => $mdaId,
            'year_id' => $yearId,
            'total_items' => $totalItems,
            'item_count' => $itemCount,
            'next_serial' => $nextSerial,
            'schedule_number' => $scheduleNumber,
        ]);

        return [
            'schedule_number' => $scheduleNumber,
            'serial_no' => (string)$nextSerial
        ];
    }

    /**
     * Helper to generate initials if column is empty.
     * Uses the first letter of every significant word, truncated to 3 characters.
     */
    private function deriveInitials($name) 
    {
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