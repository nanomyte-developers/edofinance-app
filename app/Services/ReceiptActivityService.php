<?php
namespace App\Services;

use App\Models\ReceiptActivity;
use Illuminate\Pagination\LengthAwarePaginator;

class ReceiptActivityService
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return ReceiptActivity::latest()->paginate($perPage);
    }

    public function store(array $data): ReceiptActivity
    {
        return ReceiptActivity::create($data);
    }

    public function update(ReceiptActivity $activity, array $data): ReceiptActivity
    {
        $activity->update($data);
        return $activity;
    }

    public function toggleStatus(ReceiptActivity $activity): bool
    {
        return $activity->update(['status' => $activity->status =='active' ? 'inactive' : 'active']);
        //return $activity->update(['status' => $activity->status === 1 ? 0 : 1]);
    }
}
