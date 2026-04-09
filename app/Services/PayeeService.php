<?php
namespace App\Services;

use App\Models\Payee;
use Illuminate\Pagination\LengthAwarePaginator;

class PayeeService
{
   public function listPayees(?string $search = null): LengthAwarePaginator
    {
        $query = Payee::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function storePayee(array $data): Payee
    {
        return Payee::create($data);
    }

    public function updatePayee(Payee $payee, array $data): bool
    {
        return $payee->update($data);
    }

    public function toggleStatus(Payee $payee): bool
    {
        return $payee->update(['status' => !$payee->status]);
    }
}
