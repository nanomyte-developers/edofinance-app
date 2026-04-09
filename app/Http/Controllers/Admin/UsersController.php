<?php

namespace App\Http\Controllers\Admin;

use App\Models\Mda;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Spatie\Permission\Models\Permission;

class UsersController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    // /**
    //  * Display a listing of the resource.
    //  */
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

        // Load MDA relationships for each user
        $users->getCollection()->transform(function ($user) {
            $user->load('mdas');
            return $user;
        });

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
            'allMdas' => \App\Models\Mda::all(['id', 'name'])->toArray(), // Add this line
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('admin/users/Create', [
            'allRoles' => Role::all()->pluck('name')->toArray(),
            'allPermissions' => Permission::all()->pluck('name')->toArray(),
            'allMdas' => Mda::select('id', 'name')->get()->toArray(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        // Debug what's coming from the frontend
        logger('Store User Request Data:', $request->all());
        
        try {
            // Use your service class - this is the proper way!
            $user = $this->userService->createUser($request->validated());
            
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
        // 1. Load relationships required for the form
        $user->load(['roles', 'permissions', 'mdas']);

        // 2. Wrap the user in the resource
        $userResource = new UserResource($user);
        
        // 🔥 CRITICAL DEBUG: Check the data before sending it to Inertia
        Log::info('--- USER DATA DEBUG ---');
        Log::info('User Resource Data:', $userResource->toArray(request()));
        
        // 3. Collect lists
            $allRoles = Role::all()->pluck('name')->toArray();
            $allPermissions = Permission::all()->pluck('name')->toArray();
            $allMdas = Mda::select('id', 'name')->get()->toArray(); // 🔥 FETCH MDAs

        return Inertia::render('admin/users/Edit', [
            'user' => $userResource,
            'allRoles' => $allRoles,
            'allPermissions' => $allPermissions,
            'allMdas' => $allMdas,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        // dd($user);
        // Load the user with relationships for the response if needed
        $user->load(['roles', 'permissions', 'mdas']);
        
        $updatedUser = $this->userService->updateUser($user, $request->validated());
        
        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
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

    // /**
    //  * Get user with roles and permissions for management
    //  */
    public function permissions(User $user)
    {
        // 1. Eager load necessary relations
        $userWithRelations = $user->load('roles.permissions', 'permissions'); 

        // 2. Fetch ALL available roles and permissions
        $allRoles = Role::all()->pluck('name')->toArray();
        $allPermissions = Permission::all()->pluck('name')->toArray();

        // 3. Get the user's effective permissions (direct + inherited)
        $allEffectivePermissions = $userWithRelations->getAllPermissions()->pluck('name')->toArray();
        
        // 4. FIX: Convert the UserResource to a plain array first
        $userDataArray = (new UserResource($userWithRelations))->toArray(request());

        return Inertia::render('admin/users/UserRolesPermissions', [
            // Pass the user data array without merging the effective permissions here
            'userData' => $userDataArray, 
            
            // Pass the standard available lists
            'allRoles' => $allRoles,
            'allPermissions' => $allPermissions,

            // 🔥 CRITICAL FIX: Pass Effective Permissions as a new, simple top-level array prop
            'effectivePermissionsList' => $allEffectivePermissions, 
        ]);
    }
    /**
     * Update user roles
     */
    public function updateRoles(Request $request, User $user)
    {
        // dd($request->all());
        $validated = $request->validate([
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        try {
            // This syncRoles method handles both assigning (new roles) and removing (missing roles)
            $user->syncRoles($validated['roles'] ?? []); 

            // Re-fetch the user to return updated status to the front-end (for index.vue reload)
            $user->load('roles.permissions', 'permissions'); 
            $allEffectivePermissions = $user->getAllPermissions()->pluck('name')->toArray();
            
            // return response()->json([
            //     'message' => 'Roles updated successfully',
            //     'user' => array_merge((new UserResource($user))->toArray(request()), [
            //         'all_permissions' => $allEffectivePermissions,
            //     ])
            // ]);
            return $this->index($request);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user permissions
     */
    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            // This syncPermissions method handles both assigning and removing
            // $user->syncPermissions($request->permissions);
            $user->syncPermissions($validated['permissions'] ?? []);
            
            // Re-fetch the user to return updated status
            $user->load('roles.permissions', 'permissions');
            $allEffectivePermissions = $user->getAllPermissions()->pluck('name')->toArray();

            return response()->json([
                'message' => 'Permissions updated successfully',
                'user' => array_merge((new UserResource($user))->toArray(request()), [
                    'all_permissions' => $allEffectivePermissions,
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update permissions: ' . $e->getMessage()
            ], 500);
        }
    }
}