<?php

namespace App\Services;

use App\Models\BankActivity;
use Illuminate\Pagination\LengthAwarePaginator;

class BankActivityService
{
    public function getAllPaginated($perPage = 10): LengthAwarePaginator
    {
        return BankActivity::latest()->paginate($perPage);
    }

    public function store(array $data): BankActivity
    {

        // dd($data);

        // economic_code

        // economic_code
        return BankActivity::create($data);
    }

    public function update(BankActivity $bankActivity, array $data): bool
    {
        return $bankActivity->update($data);
    }

    public function delete(BankActivity $bankActivity): bool
    {
        return $bankActivity->delete();
    }
}
