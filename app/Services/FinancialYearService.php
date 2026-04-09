<?php

namespace App\Services;

use App\Models\FinancialYear;
use Illuminate\Pagination\LengthAwarePaginator;

class FinancialYearService
{
    protected $financialYear;

    public function __construct(FinancialYear $financialYear)
    {
        $this->financialYear = $financialYear;
    }

    /**
     * Get all financial years, paginated.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(int $perPage = 10): LengthAwarePaginator
    {
        return $this->financialYear->orderBy('name', 'desc')->paginate($perPage);
    }

    /**
     * Create a new Financial Year record.
     *
     * @param array $data
     * @return FinancialYear
     */
    public function create(array $data): FinancialYear
    {
        $initialBudget = (float) $data['initial_budget'];
        $supplementaryBudget = (float) $data['supplementary_budget'];

        // Core Business Logic: Calculate derived fields on creation
        $data['total_budget'] = $initialBudget + $supplementaryBudget;
        // On creation, current_balance is typically equal to the total budget (assuming no initial transactions/funding).
        $data['total_funding'] = 0;
        $data['total_spending'] = 0;
        $data['total_recurrent_expenditure_spending'] = 0;
        $data['total_capital_expenditure_spending'] = 0;
        $data['current_ballance'] = 0;
        $data['current_balance'] = $data['total_budget'];

        return $this->financialYear->create($data);
    }

    /**
     * Update an existing Financial Year record.
     *
     * @param FinancialYear $financialYear
     * @param array $data
     * @return FinancialYear
     */
    public function update(FinancialYear $financialYear, array $data): FinancialYear
    {
        $initialBudget = (float) $data['initial_budget'];
        $supplementaryBudget = (float) $data['supplementary_budget'];

        // Core Business Logic: Recalculate total budget
        $data['total_budget'] = $initialBudget + $supplementaryBudget;

        // IMPORTANT: total_funding, total_spending, and current_balance
        // should be updated by separate methods/logic (e.g., when a Voucher is approved),
        // not directly through this update form. We only update the core budget figures.

        $financialYear->update($data);

        return $financialYear;
    }

    /**
     * Delete a Financial Year record.
     *
     * @param FinancialYear $financialYear
     * @return bool|null
     */
    public function delete(FinancialYear $financialYear): ?bool
    {
        // Add checks here (e.g., prevent deletion if total_spending > 0)
        return $financialYear->delete();
    }
}
