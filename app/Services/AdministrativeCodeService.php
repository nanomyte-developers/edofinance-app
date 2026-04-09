<?php

namespace App\Services;

use App\Models\AdministrativeCode;
//use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class AdministrativeCodeService
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return AdministrativeCode::withCount('mda')->paginate($perPage);
    }


    public function store(array $data): AdministrativeCode
    {
        return AdministrativeCode::create($data);
    }

    public function update(AdministrativeCode $code, array $data): AdministrativeCode
    {
        $code->update($data);
        return $code;
    }

    public function toggleStatus(AdministrativeCode $code): bool
    {
        return $code->update(['status' => !$code->status]);
    }
}
