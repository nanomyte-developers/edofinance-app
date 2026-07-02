<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Mda;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search', ''),
            'role' => $request->get('role'),
            'status' => $request->get('status'),
            'sort' => $request->get('sort', 'created_at'),
            'order' => $request->get('order', 'desc'),
        ];

        $users = $this->userService->getPaginatedUsers($filters);

        // Fetch all users with their categories
        $allUsers = User::select('id', 'name', 'email', 'user_category_id', 'can_be_signatory')
            ->with('userCategory')
            ->get()
            ->toArray();

        // dd($allUsers); // Debugging line to check if data is being fetched

        return Inertia::render('admin/users/index', [
            'users' => [
                'data' => UserResource::collection($users->items())->toArray($request), 
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'links' => $users->linkCollection()->toArray(),
            ],
            'allRoles' => Role::all()->pluck('name')->toArray(),
            'allPermissions' => Permission::all()->pluck('name')->toArray(),
            'allMdas' => Mda::all(['id', 'name'])->toArray(),
            'allUserCategories' => UserCategory::select('id', 'name', 'requires_signature', 'can_be_signatory')->get()->toArray(),
            'allUsers' => $allUsers, // ✅ CRITICAL: This must be here
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Fetch all users with their categories
        $allUsers = User::select('id', 'name', 'email', 'user_category_id', 'can_be_signatory')
            ->with('userCategory')
            ->get()
            ->toArray();

        // Debug - Log to check if data is being fetched
        \Log::info('Create page - Users fetched:', [
            'count' => count($allUsers),
            'sample' => array_slice($allUsers, 0, 3) // Show first 3 users
        ]);

        return Inertia::render('admin/users/Create', [
            'allRoles' => Role::all()->pluck('name')->toArray(),
            'allPermissions' => Permission::all()->pluck('name')->toArray(),
            'allMdas' => Mda::select('id', 'name')->get()->toArray(),
            'allUserCategories' => UserCategory::select('id', 'name', 'requires_signature', 'can_be_signatory')->get()->toArray(),
            'allUsers' => $allUsers, // ✅ CRITICAL: This must be here
        ]);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        logger('Store User Request Data:', $request->all());
        
        try {
            $validatedData = $request->validated();
            
            // Handle file uploads
            if ($request->hasFile('signature')) {
                $validatedData['signature'] = $request->file('signature');
            }
            
            if ($request->hasFile('passport')) {
                $validatedData['passport'] = $request->file('passport');
            }
            
            $user = $this->userService->createUser($validatedData);
            
            logger('User created with ID: ' . $user->id);
            
            return redirect()->route('users.index')->with('success', 'User created successfully.');
            
        } catch (\Exception $e) {
            logger('Error creating user: ' . $e->getMessage());
            return back()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $userWithPermissions = $this->userService->getUserWithPermissions($user->id);

        return Inertia::render('admin/users/Show', [
            'user' => new UserResource($userWithPermissions),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load(['roles', 'permissions', 'mdas', 'userCategory', 'signatory']);

        // Fetch all users with their categories
        $allUsers = User::select('id', 'name', 'email', 'user_category_id', 'can_be_signatory')
            ->with('userCategory')
            ->get()
            ->toArray();

        // Debug
        \Log::info('Edit page - Users fetched:', [
            'count' => count($allUsers)
        ]);

        return Inertia::render('admin/users/Edit', [
            'user' => new UserResource($user),
            'allRoles' => Role::all()->pluck('name')->toArray(),
            'allPermissions' => Permission::all()->pluck('name')->toArray(),
            'allMdas' => Mda::select('id', 'name')->get()->toArray(),
            'allUserCategories' => UserCategory::select('id', 'name', 'requires_signature', 'can_be_signatory')->get()->toArray(),
            'allUsers' => $allUsers, // ✅ CRITICAL: This must be here
        ]);
    }

    /**
     * ✅ FIXED: Update method - NO dd() statement
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        // dd($request->all());
        try {
            Log::info('Update User Request - Starting:', [
                'user_id' => $user->id,
                'has_signature' => $request->hasFile('signature'),
                'has_passport' => $request->hasFile('passport'),
                'all_data' => $request->except(['signature', 'passport']),
            ]);

            $validatedData = $request->validated();
            
            // Handle file uploads
            if ($request->hasFile('signature')) {
                $validatedData['signature'] = $request->file('signature');
                Log::info('Signature file received:', [
                    'name' => $request->file('signature')->getClientOriginalName(),
                    'size' => $request->file('signature')->getSize(),
                ]);
            }
            
            if ($request->hasFile('passport')) {
                $validatedData['passport'] = $request->file('passport');
                Log::info('Passport file received:', [
                    'name' => $request->file('passport')->getClientOriginalName(),
                    'size' => $request->file('passport')->getSize(),
                ]);
            }
            
            $updatedUser = $this->userService->updateUser($user, $validatedData);

            Log::info('User updated successfully:', [
                'user_id' => $updatedUser->id,
                'name' => $updatedUser->name,
                'has_signature' => !empty($updatedUser->signature),
                'has_passport' => !empty($updatedUser->passport),
            ]);
            
            return redirect()->route('users.index')
                ->with('success', 'User updated successfully.');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error updating user:', [
                'user_id' => $user->id,
                'errors' => $e->errors(),
            ]);
            
            return back()
                ->withErrors($e->errors())
                ->withInput();
                
        } catch (\Exception $e) {
            Log::error('Error updating user:', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()
                ->withInput()
                ->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            $this->userService->deleteUser($user);

            return redirect()
                ->route('users.index')
                ->with('success', "User deleted successfully.");
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get user with roles and permissions for management
     */
    public function permissions(User $user)
    {
        $userWithRelations = $user->load('roles.permissions', 'permissions', 'userCategory', 'signatory', 'mdas'); 

        $allRoles = Role::all()->pluck('name')->toArray();
        $allPermissions = Permission::all()->pluck('name')->toArray();
        $allEffectivePermissions = $userWithRelations->getAllPermissions()->pluck('name')->toArray();
        $allMdas = Mda::select('id', 'name')->get()->toArray();
        $allUserCategories = UserCategory::select('id', 'name', 'requires_signature', 'can_be_signatory')->get()->toArray();
        $allUsers = User::select('id', 'name', 'email', 'user_category_id', 'can_be_signatory')
            ->with('userCategory')
            ->get()
            ->toArray();
        
        $userDataArray = (new UserResource($userWithRelations))->toArray(request());

        return Inertia::render('admin/users/UserRolesPermissions', [
            'userData' => $userDataArray, 
            'allRoles' => $allRoles,
            'allPermissions' => $allPermissions,
            'allMdas' => $allMdas,
            'allUserCategories' => $allUserCategories,
            'allUsers' => $allUsers,
            'effectivePermissionsList' => $allEffectivePermissions, 
        ]);
    }

    /**
     * ✅ FIXED: Update user roles - returns Inertia response
     */
    public function updateRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        try {
            $user->syncRoles($validated['roles'] ?? []); 
            $user->load('roles.permissions', 'permissions'); 
            
            // ✅ Return Inertia response instead of JSON
            return redirect()->back()->with('success', 'Roles updated successfully.');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update roles: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FIXED: Update user permissions - returns Inertia response
     */
    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $user->syncPermissions($validated['permissions'] ?? []);
            $user->load('roles.permissions', 'permissions');

            // ✅ Return Inertia response instead of JSON
            return redirect()->back()->with('success', 'Permissions updated successfully.');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update permissions: ' . $e->getMessage());
        }
    }

    /**
     * Update user's signatory status
     */
    public function updateSignatoryStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'can_be_signatory' => 'required|boolean',
        ]);

        try {
            $user->update([
                'can_be_signatory' => $validated['can_be_signatory'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signatory status updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'can_be_signatory' => $user->can_be_signatory,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update signatory status: ' . $e->getMessage(),
            ], 500);
        }
    }
}