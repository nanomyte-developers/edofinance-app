<?php

namespace App\Services;

use App\Models\GlAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class JournalService
{
    /**
     * Get all journals with filters
     */
    public function getAllJournals(array $filters = [], int $perPage = 20)
    {
        $query = Journal::with(['entries', 'creator', 'approver'])
            ->select('journals.*')
            ->withCount('entries');

        // Search filter
        if (! empty($filters['search'])) {
            // dd('searching');
            $search = $filters['search'];
            $terms = preg_split('/\s+/', trim($search));
            $terms = array_filter($terms);

            if (! empty($terms)) {
                // dd($terms);
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere(function ($subQuery) use ($term) {
                            $subQuery->where('journal_number', 'like', "%{$term}%")
                                ->orWhere('description', 'like', "%{$term}%")
                                ->orWhere('remarks', 'like', "%{$term}%")
                                ->orWhere('reference_number', 'like', "%{$term}%")
                                ->orWhere('batch_number', 'like', "%{$term}%")
                                ->orWhere('status', 'like', "%{$term}%")
                                ->orWhereHas('entries', function ($entryQuery) use ($term) {
                                    $entryQuery->where('account_code', 'like', "%{$term}%")
                                        ->orWhere('account_name', 'like', "%{$term}%")
                                        ->orWhere('description', 'like', "%{$term}%");
                                })
                                ->orWhereHas('creator', function ($userQuery) use ($term) {
                                    $userQuery->where('name', 'like', "%{$term}%")
                                        ->orWhere('email', 'like', "%{$term}%");
                                });
                        });
                    }
                });
            }
        }

        // Date filters
        if (! empty($filters['date_from'])) {
            try {
                $query->whereDate('journal_date', '>=', Carbon::parse($filters['date_from'])->format('Y-m-d'));
            } catch (Exception $e) {
                Log::warning('Invalid date_from format: '.$filters['date_from']);
            }
        }

        if (! empty($filters['date_to'])) {
            try {
                $query->whereDate('journal_date', '<=', Carbon::parse($filters['date_to'])->format('Y-m-d'));
            } catch (Exception $e) {
                Log::warning('Invalid date_to format: '.$filters['date_to']);
            }
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Amount filters
        if (! empty($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (! empty($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        // Department filter
        // if (! empty($filters['department_id'])) {
        //     $query->where('department_id', $filters['department_id']);
        // }

        // Account code filter (through entries)
        if (! empty($filters['account_code'])) {
            $query->whereHas('entries', function ($q) use ($filters) {
                $q->where('account_code', $filters['account_code']);
            });
        }

        // Financial year filter
        if (! empty($filters['financial_year'])) {
            $query->where('financial_year', $filters['financial_year']);
        }

        // Sort options
        $sortBy = $filters['sort_by'] ?? 'journal_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new journal
     */
    // public function createJournal(array $data)
    // {
    //     DB::beginTransaction();

    //     try {
    //         // Generate journal number if not provided
    //         if (empty($data['journal_number'])) {
    //             $data['journal_number'] = Journal::generateJournalNumber();
    //         }

    //         // Set default financial year if not provided
    //         if (empty($data['financial_year'])) {
    //             $data['financial_year'] = Carbon::now()->year;
    //         }

    //         // Set default posting date if not provided
    //         if (empty($data['posting_date'])) {
    //             $data['posting_date'] = $data['journal_date'] ?? Carbon::now();
    //         }

    //         // Set created by
    //         $data['created_by'] = auth()->id();

    //         // Calculate totals
    //         $data['total_amount'] = $this->calculateTotalAmount($data['entries'] ?? []);
    //         $data['total_debit'] = $this->calculateTotalDebit($data['entries'] ?? []);
    //         $data['total_credit'] = $this->calculateTotalCredit($data['entries'] ?? []);

    //         // Validate journal is balanced
    //         if (! $this->isJournalBalanced($data['entries'] ?? [])) {
    //             throw new Exception('Journal is not balanced. Total debit must equal total credit.');
    //         }

    //         // Create journal
    //         $journal = Journal::create($data);

    //         // Create journal entries
    //         if (! empty($data['entries'])) {
    //             $this->createJournalEntries($journal, $data['entries']);
    //         }

    //         // Update GL account balances if journal is approved
    //         if ($journal->status === 'approved') {
    //             $this->updateGlAccountBalances($journal);
    //         }

    //         DB::commit();

    //         // Refresh the journal to get all relationships
    //         return $journal->fresh(['entries', 'creator', 'department']);

    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Log::error('Error creating journal: '.$e->getMessage());
    //         throw $e;
    //     }
    // }

    public function createJournal(array $data)
    {
        DB::beginTransaction();

        try {
            Log::info('Starting journal creation in service');
            Log::info('Service received data:', $data);

            // Generate journal number if not provided
            if (empty($data['journal_number'])) {
                $data['journal_number'] = Journal::generateJournalNumber();
                Log::info('Generated journal number:', ['journal_number' => $data['journal_number']]);
            }

            // Set default financial year if not provided
            if (empty($data['financial_year'])) {
                $data['financial_year'] = Carbon::now()->year;
            }

            // Set default posting date if not provided
            if (empty($data['posting_date'])) {
                $data['posting_date'] = $data['journal_date'] ?? Carbon::now();
            }

            // Set created by
            $data['created_by'] = auth()->id();

            // Calculate totals
            $data['total_amount'] = $this->calculateTotalAmount($data['entries'] ?? []);
            $data['total_debit'] = $this->calculateTotalDebit($data['entries'] ?? []);
            $data['total_credit'] = $this->calculateTotalCredit($data['entries'] ?? []);

            Log::info('Calculated totals:', [
                'total_amount' => $data['total_amount'],
                'total_debit' => $data['total_debit'],
                'total_credit' => $data['total_credit'],
            ]);

            // Validate journal is balanced
            if (! $this->isJournalBalanced($data['entries'] ?? [])) {
                throw new Exception('Journal is not balanced. Total debit must equal total credit.');
            }

            // Create journal
            Log::info('Creating journal record...');
            $journal = Journal::create($data);
            Log::info('Journal record created:', ['id' => $journal->id]);

            // Create journal entries
            if (! empty($data['entries'])) {
                Log::info('Creating journal entries...', ['count' => count($data['entries'])]);
                $this->createJournalEntries($journal, $data['entries']);
                Log::info('Journal entries created successfully');
            }

            DB::commit();
            Log::info('Transaction committed successfully');

            // Refresh the journal to get all relationships
            return $journal->fresh(['entries', 'creator']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in JournalService::createJournal: '.$e->getMessage());
            Log::error('Error trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Update an existing journal
     */
    public function updateJournal(Journal $journal, array $data)
    {
        DB::beginTransaction();

        try {
            // Check if journal can be edited
            if (! $journal->canEdit()  && !Auth::user()->hasRole('admin')) {
                throw new Exception('Journal cannot be edited in its current status: '.$journal->status);
            }

            // If entries are being updated, recalculate totals
            if (isset($data['entries'])) {
                $data['total_amount'] = $this->calculateTotalAmount($data['entries']);
                $data['total_debit'] = $this->calculateTotalDebit($data['entries']);
                $data['total_credit'] = $this->calculateTotalCredit($data['entries']);

                // Validate journal is balanced
                if (! $this->isJournalBalanced($data['entries'])) {
                    throw new Exception('Journal is not balanced. Total debit must equal total credit.');
                }

                // Delete existing entries
                $journal->entries()->delete();

                // Create new entries
                $this->createJournalEntries($journal, $data['entries']);
            }

            // Update journal
            $journal->update($data);

            // If status changed to approved, update GL account balances
            if ($data['status'] === 'approved' && $journal->status !== 'approved') {
                $this->updateGlAccountBalances($journal);
            }

            // If status changed from approved to something else, reverse GL account balances
            if ($journal->status === 'approved' && isset($data['status']) && $data['status'] !== 'approved') {
                $this->reverseGlAccountBalances($journal);
            }

            DB::commit();

            // Refresh the journal
            return $journal->fresh(['entries', 'creator']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating journal: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a journal
     */
    public function deleteJournal(Journal $journal): bool
    {
        DB::beginTransaction();

        try {
            // Check if journal can be deleted
            if (! $journal->canDelete()) {
                throw new Exception('Journal cannot be deleted in its current status: '.$journal->status);
            }

            // If journal was approved, reverse GL account balances
            if ($journal->status === 'approved') {
                $this->reverseGlAccountBalances($journal);
            }

            // Delete entries
            $journal->entries()->delete();

            // Delete journal
            $result = $journal->delete();

            DB::commit();

            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting journal: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve a journal
     */
    public function approveJournal(Journal $journal, array $approvalData = [])
    {
        DB::beginTransaction();

        try {
            // Check if journal can be approved
            if ($journal->status === 'approved') {
                throw new Exception('Journal is already approved.');
            }

            // Update journal status and approval data
            $journal->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now(),
                'remarks' => $approvalData['remarks'] ?? $journal->remarks,
            ]);

            // Update GL account balances
            $this->updateGlAccountBalances($journal);

            DB::commit();

            return $journal->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error approving journal: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject a journal
     */
    public function rejectJournal(Journal $journal, string $reason)
    {
        $journal->update([
            'status' => 'rejected',
            'remarks' => $reason.(empty($journal->remarks) ? '' : ' | '.$journal->remarks),
        ]);

        return $journal->fresh();
    }

    /**
     * Get GL accounts for dropdown
     */
    // public function getGlAccounts($filters = [])
    // {
    //     $query = GlAccount::active()->orderBy('account_code');

    //     if (! empty($filters['account_type'])) {
    //         $query->where('account_type', $filters['account_type']);
    //     }

    //     if (! empty($filters['search'])) {
    //         $query->where(function ($q) use ($filters) {
    //             $q->where('account_code', 'like', "%{$filters['search']}%")
    //                 ->orWhere('account_name', 'like', "%{$filters['search']}%");
    //         });
    //     }

    //     return $query->get()->map(function ($account) {
    //         return [
    //             'id' => $account->id,
    //             'account_code' => $account->account_code,
    //             'account_name' => $account->account_name,
    //             'full_name' => $account->full_name,
    //             'account_type' => $account->account_type,
    //             'normal_balance' => $account->normal_balance,
    //             'current_balance' => $account->current_balance,
    //             'is_parent' => $account->isParent(),
    //             'formatted_type' => $account->formatted_account_type,
    //             'searchLabel' => "{$account->account_code} - {$account->account_name} [{$account->formatted_account_type}]",
    //         ];
    //     });
    // }

    /**
     * Get statistics
     */
    public function getStatistics()
    {
        return [
            'total_journals' => Journal::count(),
            'total_amount' => Journal::where('status', 'approved')->sum('total_amount'),
            'pending_count' => Journal::pending()->count(),
            'approved_count' => Journal::approved()->count(),
            'draft_count' => Journal::draft()->count(),
            'count_by_month' => Journal::selectRaw('DATE_FORMAT(journal_date, "%Y-%m") as month, COUNT(*) as count')
                ->whereYear('journal_date', Carbon::now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->pluck('count', 'month'),
            'top_accounts' => JournalEntry::select('account_code', 'account_name', DB::raw('SUM(debit_amount + credit_amount) as total'))
                ->whereHas('journal', function ($q) {
                    $q->where('status', 'approved');
                })
                ->groupBy('account_code', 'account_name')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        return [
            'statuses' => [
                'draft' => 'Draft',
                'saved' => 'Saved',
                'pending' => 'Pending Approval',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'returned' => 'Returned',
                'sent back' => 'Sent Back',
                'declined' => 'Declined',
                'cancelled' => 'Cancelled',
            ],
            'account_types' => [
                'asset' => 'Asset',
                'liability' => 'Liability',
                'equity' => 'Equity',
                'income' => 'Income',
                'expense' => 'Expense',
                'cost' => 'Cost of Goods Sold',
                'revenue' => 'Revenue',
            ],
            'financial_years' => Journal::select('financial_year')
                ->distinct()
                ->whereNotNull('financial_year')
                ->orderBy('financial_year', 'desc')
                ->pluck('financial_year'),
            // 'departments' => \App\Models\Department::select(['id', 'name', 'code'])
            //     ->where('is_active', true)
            //     ->orderBy('name')
            //     ->get()
            // ->map(function ($dept) {
            //     return [
            //         'id' => $dept->id,
            //         'name' => $dept->name,
            //         'code' => $dept->code,
            //         'searchLabel' => "{$dept->code} - {$dept->name}",
            //     ];
            // }),
        ];
    }

    /**
     * Create journal entries
     */
    private function createJournalEntries(Journal $journal, array $entries)
    {
        // $lineNumber = 1;
        // foreach ($entries as $entry) {
        //     // Get GL account details
        //     $glAccount = GlAccount::where('account_code', $entry['account_code'])->first();

        //     if (! $glAccount) {
        //         throw new Exception("GL Account not found: {$entry['account_code']}");
        //     }

        //     // Create journal entry
        //     JournalEntry::create([
        //         'journal_id' => $journal->id,
        //         'account_code' => $entry['account_code'],
        //         'account_name' => $glAccount->account_name,
        //         'description' => $entry['description'] ?? '',
        //         'debit_amount' => $entry['debit_amount'] ?? 0,
        //         'credit_amount' => $entry['credit_amount'] ?? 0,
        //         'line_number' => $lineNumber++,
        //         'cost_center' => $entry['cost_center'] ?? null,
        //         'project_code' => $entry['project_code'] ?? null,
        //         'department_id' => $entry['department_id'] ?? $journal->department_id,
        //         'reference' => $entry['reference'] ?? null,
        //         'tax_code' => $entry['tax_code'] ?? null,
        //         'tax_amount' => $entry['tax_amount'] ?? 0,
        //     ]);
        // }
        $lineNumber = 1;
        foreach ($entries as $entry) {
            // Create journal entry without looking up GL account
            \App\Models\JournalEntry::create([
                'journal_id' => $journal->id,
                'account_code' => $entry['account_code'],
                'account_name' => '', // Leave empty or use description
                'description' => $entry['description'] ?? '',
                'debit_amount' => $entry['debit_amount'] ?? 0,
                'credit_amount' => $entry['credit_amount'] ?? 0,
                'line_number' => $lineNumber++,
                'cost_center' => $entry['cost_center'] ?? null,
                'project_code' => $entry['project_code'] ?? null,
                'department_id' => $entry['department_id'] ?? $journal->department_id,
                'reference' => $entry['reference'] ?? null,
                'tax_code' => $entry['tax_code'] ?? null,
                'tax_amount' => $entry['tax_amount'] ?? 0,
            ]);
        }
    }

    /**
     * Update GL account balances
     */
    private function updateGlAccountBalances(Journal $journal)
    {
        // foreach ($journal->entries as $entry) {
        //     $glAccount = GlAccount::where('account_code', $entry->account_code)->first();

        //     if ($glAccount) {
        //         $balanceChange = 0;

        //         if ($glAccount->normal_balance === 'debit') {
        //             // For debit normal balance accounts, debit increases, credit decreases
        //             $balanceChange = $entry->debit_amount - $entry->credit_amount;
        //         } else {
        //             // For credit normal balance accounts, credit increases, debit decreases
        //             $balanceChange = $entry->credit_amount - $entry->debit_amount;
        //         }

        //         $glAccount->increment('current_balance', $balanceChange);
        //     }
        // }
    }

    /**
     * Reverse GL account balances
     */
    private function reverseGlAccountBalances(Journal $journal)
    {
        // foreach ($journal->entries as $entry) {
        //     $glAccount = GlAccount::where('account_code', $entry->account_code)->first();

        //     if ($glAccount) {
        //         $balanceChange = 0;

        //         if ($glAccount->normal_balance === 'debit') {
        //             // For debit normal balance accounts, debit increases, credit decreases
        //             $balanceChange = $entry->debit_amount - $entry->credit_amount;
        //         } else {
        //             // For credit normal balance accounts, credit increases, debit decreases
        //             $balanceChange = $entry->credit_amount - $entry->debit_amount;
        //         }

        //         $glAccount->decrement('current_balance', $balanceChange);
        //     }
        // }
    }

    /**
     * Calculate total amount
     */
    private function calculateTotalAmount(array $entries): float
    {
        return array_reduce($entries, function ($total, $entry) {
            return $total + ($entry['debit_amount'] ?? 0) + ($entry['credit_amount'] ?? 0);
        }, 0);
    }

    /**
     * Calculate total debit
     */
    private function calculateTotalDebit(array $entries): float
    {
        return array_reduce($entries, function ($total, $entry) {
            return $total + ($entry['debit_amount'] ?? 0);
        }, 0);
    }

    /**
     * Calculate total credit
     */
    private function calculateTotalCredit(array $entries): float
    {
        return array_reduce($entries, function ($total, $entry) {
            return $total + ($entry['credit_amount'] ?? 0);
        }, 0);
    }

    /**
     * Check if journal is balanced
     */
    private function isJournalBalanced(array $entries): bool
    {
        $totalDebit = $this->calculateTotalDebit($entries);
        $totalCredit = $this->calculateTotalCredit($entries);

        return abs($totalDebit - $totalCredit) < 0.01; // Allow small floating point differences
    }

    /**
     * Get journal summary
     */
    public function getJournalSummary($journalId)
    {
        $journal = Journal::with(['entries.glAccount', 'creator', 'approver'])
            ->findOrFail($journalId);

        $summary = [
            'journal' => $journal,
            'debit_total' => $journal->entries->sum('debit_amount'),
            'credit_total' => $journal->entries->sum('credit_amount'),
            'entry_count' => $journal->entries->count(),
            'account_summary' => $journal->entries->groupBy('account_code')->map(function ($entries) {
                return [
                    'account_code' => $entries->first()->account_code,
                    'account_name' => $entries->first()->account_name,
                    'total_debit' => $entries->sum('debit_amount'),
                    'total_credit' => $entries->sum('credit_amount'),
                    'entry_count' => $entries->count(),
                ];
            })->values(),
        ];

        return $summary;
    }

    /**
     * Import journals from CSV
     */
    public function importJournalsFromCsv($filePath, array $options = [])
    {
        // Implementation for CSV import
        // This would parse the CSV and create journals
        // Return summary of import results
    }

    /**
     * Export journals to CSV
     */
    public function exportJournalsToCsv(array $filters = [])
    {
        // Implementation for CSV export
        // Return file path or stream
    }

    /**
     * Create recurring journal
     */
    public function createRecurringJournal(Journal $journal, array $recurringData)
    {
        // Create a recurring journal template
        // This would set up the journal to be duplicated at specified intervals
    }

    /**
     * Process recurring journals
     */
    public function processRecurringJournals()
    {
        // Process all due recurring journals
        // Create new journal instances based on templates
    }

    /**
     * Validate journal entries
     */
    // public function validateJournalEntries(array $entries): array
    // {
    //     $errors = [];

    //     // Check if at least two entries
    //     if (count($entries) < 2) {
    //         $errors[] = 'Journal must have at least two entries.';
    //     }

    //     // Calculate totals
    //     $totalDebit = 0;
    //     $totalCredit = 0;

    //     foreach ($entries as $index => $entry) {
    //         $lineNumber = $index + 1;

    //         // Check account code
    //         if (empty($entry['account_code'])) {
    //             $errors[] = "Line {$lineNumber}: Account code is required.";

    //             continue;
    //         }

    //         // Check if account exists
    //         $glAccount = GlAccount::where('account_code', $entry['account_code'])->first();
    //         if (! $glAccount) {
    //             $errors[] = "Line {$lineNumber}: Account code '{$entry['account_code']}' not found.";
    //         } elseif (! $glAccount->is_active) {
    //             $errors[] = "Line {$lineNumber}: Account '{$entry['account_code']}' is not active.";
    //         }

    //         // Check amounts
    //         $debit = $entry['debit_amount'] ?? 0;
    //         $credit = $entry['credit_amount'] ?? 0;

    //         if ($debit < 0 || $credit < 0) {
    //             $errors[] = "Line {$lineNumber}: Amounts cannot be negative.";
    //         }

    //         if ($debit > 0 && $credit > 0) {
    //             $errors[] = "Line {$lineNumber}: Entry cannot have both debit and credit amounts.";
    //         }

    //         if ($debit == 0 && $credit == 0) {
    //             $errors[] = "Line {$lineNumber}: Entry must have either debit or credit amount.";
    //         }

    //         $totalDebit += $debit;
    //         $totalCredit += $credit;
    //     }

    //     // Check if journal is balanced
    //     if (abs($totalDebit - $totalCredit) > 0.01) {
    //         $errors[] = "Journal is not balanced. Total Debit: {$totalDebit}, Total Credit: {$totalCredit}";
    //     }

    //     return $errors;
    // }

    /**
     * Validate journal entries
     */
    public function validateJournalEntries(array $entries): array
    {
        $errors = [];

        // Check if at least two entries
        if (count($entries) < 2) {
            $errors[] = 'Journal must have at least two entries.';
        }

        // Calculate totals
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $index => $entry) {
            $lineNumber = $index + 1;

            // Check account code
            if (empty($entry['account_code'])) {
                $errors[] = "Line {$lineNumber}: Account code is required.";

                continue;
            }

            // Check if account exists in economy code items
            $economyCodeItem = \App\Models\EconomyCodeItem::where('code', $entry['account_code'])
                ->where('status', 'active')
                ->first();

            // Also check GL accounts for backward compatibility
            // $glAccount = \App\Models\GlAccount::where('account_code', $entry['account_code'])->first();

            // if (! $economyCodeItem && ! $glAccount) {
            //     $errors[] = "Line {$lineNumber}: Account code '{$entry['account_code']}' not found.";
            // }

            // Check amounts
            $debit = $entry['debit_amount'] ?? 0;
            $credit = $entry['credit_amount'] ?? 0;

            if ($debit < 0 || $credit < 0) {
                $errors[] = "Line {$lineNumber}: Amounts cannot be negative.";
            }

            if ($debit > 0 && $credit > 0) {
                $errors[] = "Line {$lineNumber}: Entry cannot have both debit and credit amounts.";
            }

            if ($debit == 0 && $credit == 0) {
                $errors[] = "Line {$lineNumber}: Entry must have either debit or credit amount.";
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        // Check if journal is balanced
        if (abs($totalDebit - $totalCredit) > 0.01) {
            $errors[] = "Journal is not balanced. Total Debit: {$totalDebit}, Total Credit: {$totalCredit}";
        }

        return $errors;
    }
}
