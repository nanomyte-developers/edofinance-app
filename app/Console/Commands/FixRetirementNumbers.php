<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRetirementNumbers extends Command
{
    // Added {--dry-run} option to the signature
    protected $signature = 'vouchers:fix-years {--dry-run : Display changes without updating the database}';

    protected $description = 'Updates retirement numbers ending in 2026 to end in 2025';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $tableName = 'retirement_vouchers';
        $column = 'retirement_number';

        $vouchers = DB::table($tableName)
            ->where($column, 'LIKE', '%2026')
            ->get();

        if ($vouchers->isEmpty()) {
            $this->info('No records found ending in 2026.');
            return;
        }

        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE: No database changes will be saved ---');
        }

        $this->withProgressBar($vouchers, function ($voucher) use ($isDryRun, $tableName, $column) {
            $oldNumber = $voucher->$column;
            // Regex ensures we only replace "2026" at the very END ($) of the string
            $newNumber = preg_replace('/2026$/', '2025', $oldNumber);

            if ($isDryRun) {
                // Using line to show the transformation in the console
                $this->line("\n[DRY RUN] ID {$voucher->id}: {$oldNumber} -> {$newNumber}");
            } else {
                DB::table($tableName)
                    ->where('id', $voucher->id)
                    ->update([$column => $newNumber]);
            }
        });

        $this->newLine();
        $this->info($isDryRun ? 'Dry run complete.' : 'Updates applied successfully!');
    }
}
