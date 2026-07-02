<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCategory;
use App\Http\Resources\UserResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserCategoryService
{
    public function getPaginatedCategories(array $filters)
    {
        $query = UserCategory::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';

        return $query->orderBy($sortField, $sortOrder)->paginate(100);
    }

    public function createCategory(array $data)
    {
        return UserCategory::create($data);
    }

    public function updateCategory($id, array $data)
    {
        $category = UserCategory::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function deleteCategory($id)
    {
        $category = UserCategory::findOrFail($id);
        
        // Check if category has users
        if ($category->users()->count() > 0) {
            throw new \Exception('Cannot delete category with assigned users.');
        }
        
        return $category->delete();
    }
}