<?php

namespace App\Services;

use App\Models\Remittance;
use App\Models\BankActivity;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RemittanceService
{
    /**
     * Get all remittances with filters
     */
    public function getAllRemittances(array $filters = [], int $perPage = 20)
    {
        $query = Remittance::with(['sourceBank', 'destinationBank']);
        
        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $terms = preg_split('/\s+/', trim($search));
            $terms = array_filter($terms); 
            
            if (!empty($terms)) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere(function ($subQuery) use ($term) {
                            $subQuery->where('receipt_number', 'like', "%{$term}%")
                                // ->orWhere('treasury', 'like', "%{$term}%")
                                ->orWhere('amount', 'like', "%{$term}%")
                                ->orWhere('narration', 'like', "%{$term}%")
                                ->orWhere('status', 'like', "%{$term}%")
                                // Search through source bank relationship
                                ->orWhereHas('sourceBank', function ($bankQuery) use ($term) {
                                    $bankQuery->where('bank_name', 'like', "%{$term}%")
                                        ->orWhere('title', 'like', "%{$term}%")
                                        ->orWhere('account_number', 'like', "%{$term}%")
                                        ->orWhere('tag', 'like', "%{$term}%");
                                })
                                // Search through destination bank relationship
                                ->orWhereHas('destinationBank', function ($bankQuery) use ($term) {
                                    $bankQuery->where('bank_name', 'like', "%{$term}%")
                                        ->orWhere('title', 'like', "%{$term}%")
                                        ->orWhere('account_number', 'like', "%{$term}%")
                                        ->orWhere('tag', 'like', "%{$term}%");
                                });
                        });
                    }
                });
            }
        }
        
        // Individual bank filters (if you have these in your filters)
        if (!empty($filters['source_bank_id'])) {
            $query->where('source_bank_id', $filters['source_bank_id']);
        }
        
        if (!empty($filters['destination_bank_id'])) {
            $query->where('destination_bank_id', $filters['destination_bank_id']);
        }
        
        // Treasury filter (if needed)
        // if (!empty($filters['treasury'])) {
        //     $query->where('treasury', $filters['treasury']);
        // }
        
        // Date filters
        if (!empty($filters['date_from'])) {
            try {
                $query->whereDate('transfer_date', '>=', Carbon::parse($filters['date_from'])->format('Y-m-d'));
            } catch (\Exception $e) {
                Log::warning('Invalid date_from format: ' . $filters['date_from']);
            }
        }
        
        if (!empty($filters['date_to'])) {
            try {
                $query->whereDate('transfer_date', '<=', Carbon::parse($filters['date_to'])->format('Y-m-d'));
            } catch (\Exception $e) {
                Log::warning('Invalid date_to format: ' . $filters['date_to']);
            }
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Amount filters
        if (!empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }
        
        if (!empty($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }
        
        // Order by latest
        $query->latest();
        
        return $query->paginate($perPage);
    }

    /**
     * Create a new remittance
     */
    public function createRemittance(array $data)
    {
        // Get bank details if IDs are provided
        if (!empty($data['source_bank_id'])) {
            $sourceBank = BankActivity::find($data['source_bank_id']);
            if ($sourceBank) {
                $data['source_bank_details'] = json_encode([
                    'bank_name' => $sourceBank->bank_name,
                    'account_name' => $sourceBank->title,
                    'account_number' => $sourceBank->account_number,
                    'bank_code' => $sourceBank->bank_code,
                    'branch' => $sourceBank->branch,
                ]);
            }
        }

        if (!empty($data['destination_bank_id'])) {
            $destBank = BankActivity::find($data['destination_bank_id']);
            if ($destBank) {
                $data['destination_bank_details'] = json_encode([
                    'bank_name' => $destBank->bank_name,
                    'account_name' => $destBank->title,
                    'account_number' => $destBank->account_number,
                    'bank_code' => $destBank->bank_code,
                    'branch' => $destBank->branch,
                ]);
            }
        }

        // Create amount in words
        $data['amount_in_words'] = $this->convertToWords($data['amount']);
        
        return Remittance::create($data);
    }
    
    /**
     * Update an existing remittance
     */
    // public function updateRemittance(Remittance $remittance, array $data)
    // {
    //     // Get bank details if IDs are provided
    //     if (!empty($data['source_bank_id'])) {
    //         $sourceBank = BankActivity::find($data['source_bank_id']);
    //         if ($sourceBank) {
    //             $data['source_bank_details'] = json_encode([
    //                 'bank_name' => $sourceBank->bank_name,
    //                 'account_name' => $sourceBank->title,
    //                 'account_number' => $sourceBank->account_number,
    //                 'bank_code' => $sourceBank->bank_code,
    //                 'branch' => $sourceBank->branch,
    //             ]);
    //         }
    //     }
        
    //     if (!empty($data['destination_bank_id'])) {
    //         $destBank = BankActivity::find($data['destination_bank_id']);
    //         if ($destBank) {
    //             $data['destination_bank_details'] = json_encode([
    //                 'bank_name' => $destBank->bank_name,
    //                 'account_name' => $destBank->title,
    //                 'account_number' => $destBank->account_number,
    //                 'bank_code' => $destBank->bank_code,
    //                 'branch' => $destBank->branch,
    //             ]);
    //         }
    //     }
        
    //     // Update amount in words
    //     $data['amount_in_words'] = $this->convertToWords($data['amount']);
        
    //     return $remittance->update($data);
    // }

    /**
 * Update an existing remittance
 */
public function updateRemittance(Remittance $remittance, array $data)
{
    // Get bank details if IDs are provided
    if (!empty($data['source_bank_id'])) {
        $sourceBank = BankActivity::find($data['source_bank_id']);
        if ($sourceBank) {
            $data['source_bank_details'] = json_encode([
                'bank_name' => $sourceBank->bank_name,
                'account_name' => $sourceBank->title,
                'account_number' => $sourceBank->account_number,
                'bank_code' => $sourceBank->bank_code,
                'branch' => $sourceBank->branch,
            ]);
        }
    }
    
    if (!empty($data['destination_bank_id'])) {
        $destBank = BankActivity::find($data['destination_bank_id']);
        if ($destBank) {
            $data['destination_bank_details'] = json_encode([
                'bank_name' => $destBank->bank_name,
                'account_name' => $destBank->title,
                'account_number' => $destBank->account_number,
                'bank_code' => $destBank->bank_code,
                'branch' => $destBank->branch,
            ]);
        }
    }
    
    // Update amount in words
    $data['amount_in_words'] = $this->convertToWords($data['amount']);
    
    // FIX: Update the remittance and return the object, not boolean
    $remittance->update($data);
    
    // Return the refreshed remittance object
    return $remittance->fresh();
}
    
    /**
     * Delete a remittance
     */
    public function deleteRemittance(Remittance $remittance): bool
    {
        return $remittance->delete();
    }
    
    /**
     * Get bank activities for dropdown
     */
    public function getBankActivities()
    {
        return BankActivity::select([
            'id',
            'tag',
            'bank_name',
            'title',
            'account_number',
            'status'
        ])
        ->where('status', 'active')
        ->orderBy('bank_name')
        ->orderBy('title')
        ->get();
    }
    
    /**
     * Get statistics
     */
    public function getStatistics()
    {
        return [
            'total_amount' => Remittance::sum('amount'),
            'average_amount' => Remittance::avg('amount'),
            'count_by_status' => Remittance::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            'recent_count' => Remittance::whereDate('created_at', '>=', Carbon::now()->subDays(30))->count(),
        ];
    }
    
    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        return [
            // 'treasuries' => Remittance::select('treasury')
            //     ->distinct()
            //     ->whereNotNull('treasury')
            //     ->orderBy('treasury')
            //     ->pluck('treasury'),
            // 'source_banks' => Remittance::select('source_bank')
            //     ->distinct()
            //     ->whereNotNull('source_bank')
            //     ->orderBy('source_bank')
            //     ->pluck('source_bank'),
            // 'destination_banks' => Remittance::select('destination_bank')
            //     ->distinct()
            //     ->whereNotNull('destination_bank')
            //     ->orderBy('destination_bank')
            //     ->pluck('destination_bank'),
            'statuses' => [
                'draft' => 'Draft',
                'pending' => 'Pending Approval',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'completed' => 'Completed'
            ],
        ];
    }
    
    /**
     * Convert number to words
     */
    private function convertToWords($number): string
    {
        if ($number == 0) {
            return 'Zero Naira Only';
        }
        
        $whole = floor($number);
        $fraction = round(($number - $whole) * 100);
        
        $words = $this->convertNumber($whole) . ' Naira';
        
        if ($fraction > 0) {
            $words .= ' and ' . $this->convertNumber($fraction) . ' Kobo';
        }
        
        return $words . ' Only';
    }
    
    /**
     * Helper function to convert number to words
     */
    private function convertNumber($number): string
    {
        $ones = array(
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen'
        );
        
        $tens = array(
            2 => 'Twenty',
            3 => 'Thirty',
            4 => 'Forty',
            5 => 'Fifty',
            6 => 'Sixty',
            7 => 'Seventy',
            8 => 'Eighty',
            9 => 'Ninety'
        );
        
        if ($number < 20) {
            return $ones[$number];
        } elseif ($number < 100) {
            $ten = floor($number / 10);
            $unit = $number % 10;
            return $tens[$ten] . ($unit > 0 ? ' ' . $ones[$unit] : '');
        } elseif ($number < 1000) {
            $hundred = floor($number / 100);
            $remainder = $number % 100;
            return $ones[$hundred] . ' Hundred' . ($remainder > 0 ? ' and ' . $this->convertNumber($remainder) : '');
        } elseif ($number < 1000000) {
            $thousand = floor($number / 1000);
            $remainder = $number % 1000;
            return $this->convertNumber($thousand) . ' Thousand' . ($remainder > 0 ? ' ' . $this->convertNumber($remainder) : '');
        } elseif ($number < 1000000000) {
            $million = floor($number / 1000000);
            $remainder = $number % 1000000;
            return $this->convertNumber($million) . ' Million' . ($remainder > 0 ? ' ' . $this->convertNumber($remainder) : '');
        }
        
        return 'Large Amount';
    }
}