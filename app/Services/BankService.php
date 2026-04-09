<?php
namespace App\Services;

use App\Models\Bank;

class BankService
{
    public function getAllPaginated($search = null, $perPage = 10)
    {
        $query = Bank::query();
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('initials', 'like', "%{$search}%");
            });
        }
        
        return $query->latest()->paginate($perPage);
    }

    public function storeBank(array $data)
    {
        return Bank::create($data);
    }

    public function updateBank(Bank $bank, array $data)
    {
        $bank->update($data);
        return $bank;
    }

    public function toggleStatus(Bank $bank)
    {
        $bank->status = $bank->status === 1 ? 0 : 1;
        $bank->save();
        return $bank;
    }
}
