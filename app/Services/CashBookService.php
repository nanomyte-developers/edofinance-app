<?php
namespace App\Services;

use App\Models\CashBookBalanceBfw;

class CashBookService
{
    public function getAllPaginated($perPage = 100)
    {
        //return CashBookBalanceBfw::latest()->paginate($perPage);
        $search = request('search');

      return CashBookBalanceBfw::with('bankActivity')
        ->when($search, function ($query, $search) {
            $query->whereHas('bankActivity', function ($q) use ($search) {
                $q->where('tag', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('financial_year', 'like', "%{$search}%");
            })
            ->orWhere('amount', 'like', "%{$search}%");
        })
        ->latest()
        ->paginate($perPage)
        ->withQueryString(); // Keeps search param during pagination
    }

    public function storeOrUpdate(array $data, $id = null)
    {
        return CashBookBalanceBfw::updateOrCreate(['id' => $id], $data);
    }

    public function toggleStatus($id)
    {
        $record = CashBookBalanceBfw::findOrFail($id);
        $record->status = $record->status === 1 ? 0 : 1;
        return $record->save();
    }
}
