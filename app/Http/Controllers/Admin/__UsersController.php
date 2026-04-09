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
    // public function index(Request $request)
    // {
    //     $filters = [
    //         'search' => $request->get('search', ''),
    //         'role' => $request->get('role'),
    //         'status' => $request->get('status'),
    //         'sort' => $request->get('sort', 'created_at'),
    //         'order' => $request->get('order', 'desc'),
    //     ];

    //     $users = $this->userService->getPaginatedUsers($filters);

    //     return Inertia::render('admin/users/index', [
    //         'users' => [
    //             'data' => UserResource::collection($users->items())->toArray($request), // Add ->toArray($request)
    //             'total' => $users->total(),
    //             'current_page' => $users->currentPage(),
    //             'per_page' => $users->perPage(),
    //             'links' => $users->linkCollection()->toArray(),
    //         ],
    //         'filters' => $filters,
    //         // 'all_roles' => $this->userService->getAllRoles(),
    //         // FIX: Pass full Role and Permission objects, matching modal prop names
    //         'allRoles' => Role::select('id', 'name')->get(),
    //         'allPermissions' => Permission::select('id', 'name')->get(),
    //     ]);
    // }

    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     // return Inertia::render('admin/users/Create', [
    //     //     'all_roles' => $this->userService->getAllRoles(),
    //     // ]);
    //     $allRoles = Role::all()->pluck('name')->toArray();
    //     $allPermissions = Permission::all()->pluck('name')->toArray();
        
    //     // Fetch MDAs as objects for the frontend MdaAssignment.vue
    //     $allMdas = Mda::select('id', 'name')->get()->toArray();
        
    //     return Inertia::render('admin/users/Create', [
    //         'allRoles' => $allRoles,
    //         'allPermissions' => $allPermissions,
    //         'allMdas' => $allMdas, // NEW: Pass MDAs
    //     ]);
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(StoreUserRequest $request)
    // {
    //     try {
    //         $user = $this->userService->createUser($request->validated());

    //         return redirect()
    //             ->route('users.index')
    //             ->with('success', "User {$user->name} created successfully.");
    //     } catch (\Exception $e) {
    //         return back()
    //             ->with('error', 'Failed to create user: ' . $e->getMessage());
    //     }
    // }

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

    return Inertia::render('admin/users/index', [
        'users' => [
            'data' => UserResource::collection($users->items())->toArray($request), 
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'links' => $users->linkCollection()->toArray(),
        ],
        // 'roles' => Role::all()->pluck('name')->toArray(),
        'allRoles' => Role::all()->pluck('name')->toArray(),
        'allPermissions' => Permission::all()->pluck('name')->toArray(),
        'filters' => $filters,
    ]);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $allRoles = Role::all()->pluck('name')->toArray();
        $allPermissions = Permission::all()->pluck('name')->toArray();
        
        // Fetch ALL MDAs (ID and Name are needed for Picklist)
        $allMdas = Mda::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('admin/users/Create', [
            'allRoles' => $allRoles,
            'allPermissions' => $allPermissions,
            'allMdas' => $allMdas, // Pass all MDAs to the form
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        // Handle all assignment data passed from UserForm.vue
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'email_verified_at' => $request->boolean('email_verified') ? now() : null,
            ]);

            if (!empty($request->roles)) {
                $user->syncRoles($request->roles);
            }
            if (!empty($request->permissions)) {
                $user->syncPermissions($request->permissions);
            }
            // Assuming the User model has an mdas() relationship
            if (!empty($request->mdas)) {
                $user->mdas()->sync($request->mdas); 
            }

            return $user;
        });

        return redirect()->route('users.index')->with('success', 'User created successfully.');
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
        $user->load('roles.permissions', 'permissions'); // Load existing roles/permissions

        $allRoles = Role::all()->pluck('name')->toArray();
        $allPermissions = Permission::all()->pluck('name')->toArray();

        // Fetch ALL MDAs
        $allMdas = Mda::select('id', 'name')->orderBy('name')->get(); 

        // Get the assigned MDA IDs
        $assignedMdaIds = DB::table('mda_user')->where('user_id', $user->id)->pluck('mda_id')->toArray();
        
        // Convert user resource to array and inject assigned MDA IDs
        $userArray = (new UserResource($user))->toArray(request());
        $userArray['selected_mdas'] = $assignedMdaIds; 

        return Inertia::render('admin/users/Create', [
            'user' => $userArray, // Pass modified user data
            'allRoles' => $allRoles,
            'allPermissions' => $allPermissions,
            'allMdas' => $allMdas, // Pass all MDAs
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $user = $this->userService->updateUser($user, $request->validated());

            return redirect()
                ->route('users.index')
                ->with('success', "User {$user->name} updated successfully.");
        } catch (\Exception $e) {
            return back()
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

    // /**
    //  * Get user with roles and permissions for management
    //  */
    // public function permissions(User $user)
    // {
    //     // ... (Your existing logic to load $userWithRelations)
    //     $userWithRelations = $user->load([
    //         'roles' => function ($query) {
    //             $query->select('id', 'name');
    //         },
    //         'permissions' => function ($query) {
    //             $query->select('id', 'name');
    //         }
    //     ]);

    //     // Check if these return data
    //     $allRoles = Role::select('id', 'name')->get(); 
    //     $allPermissions = Permission::select('id', 'name')->get();
        
    //     // --- TEMPORARY DEBUG CHECK ---
    //     // dd($allRoles, $allPermissions); 
    //     // This should display a collection of your roles and permissions.
    //     // If they show '[]' or Collections containing items with blank 'name' fields, 
    //     // the problem is confirmed to be in your database data.
    //     // -----------------------------

    //     return Inertia::render('admin/users/UserRolesPermissions', [
    //         'userData' => new UserResource($userWithRelations),
    //         'allRoles' => $allRoles,
    //         'allPermissions' => $allPermissions,
    //     ]);
    // }

    // /**
    //  * Update user roles
    //  */
    // public function updateRoles(Request $request, User $user)
    // {
    //     $request->validate([
    //         'roles' => 'array',
    //         'roles.*' => 'string|exists:roles,name',
    //     ]);

    //     try {
    //         $user->syncRoles($request->roles);

    //         // return response()->json([
    //         //     'message' => 'Roles updated successfully',
    //         //     'user' => new UserResource($user->load('roles', 'permissions'))
    //         // ]);
    //         return redirect()->back()->with('success', 'Roles updated successfully.');

    //     } catch (\Exception $e) {
    //         // return response()->json([
    //         //     'message' => 'Failed to update roles: ' . $e->getMessage()
    //         // ], 500);
    //         return redirect()->back()->with('error', 'Failed to update roles: ' . $e->getMessage());
    //     }
    // }

    // /**
    //  * Update user permissions
    //  */
    // public function updatePermissions(Request $request, User $user)
    // {

    //         $val = $request->validate([
    //             'permissions' => 'array',
    //             'permissions.*' => 'string|exists:permissions,name',
    //         ]);

    //         // dd($val, 'Validation passed!');

    //         try {
    //             $check = $user->syncPermissions($request->permissions);

    //             // dd($check, 'Permissions synced!');
                
    //             // FIX: Return a redirect back instead of JSON
    //             return redirect()->back()->with('success', 'Permissions updated successfully.');

    //         } catch (\Exception $e) {
    //             return redirect()->back()->with('error', 'Failed to update permissions: ' . $e->getMessage());
    //         }
    // }

    // /**
    //  * Show the user permissions modal.
    //  */
    // public function permissions(User $user)
    // {
    //     // Eager load necessary relations for the modal/UserResource
    //     $userWithRelations = $user->load('roles.permissions', 'permissions', 'mdas'); 

    //     $allRoles = Role::all()->pluck('name')->toArray();
    //     $allPermissions = Permission::all()->pluck('name')->toArray();

    //     // 🚨 CRUCIAL FIX: Get the user's effective permissions (direct + inherited)
    //     $allEffectivePermissions = $userWithRelations->getAllPermissions()->pluck('name')->toArray();
        
    //     return Inertia::render('admin/users/UserRolesPermissions', [
    //         // Pass the effective permissions list alongside user data
    //         'userData' => array_merge((new UserResource($userWithRelations))->toArray(request()), [
    //             'all_permissions' => $allEffectivePermissions,
    //         ]),
    //         'allRoles' => $allRoles,
    //         'allPermissions' => $allPermissions,
    //     ]);
    // }

    // /**
    //  * Update user roles
    //  */
    // public function updateRoles(Request $request, User $user)
    // {
    //     $request->validate([
    //         'roles' => 'array',
    //         'roles.*' => 'string|exists:roles,name',
    //     ]);

    //     try {
    //         $user->syncRoles($request->roles);

    //         // Re-fetch user with updated roles/permissions/effective permissions
    //         $user->load('roles.permissions', 'permissions', 'mdas');
    //         $allEffectivePermissions = $user->getAllPermissions()->pluck('name')->toArray();
            
    //         return response()->json([
    //             'message' => 'Roles updated successfully',
    //             'user' => array_merge((new UserResource($user))->toArray(request()), [
    //                 'all_permissions' => $allEffectivePermissions,
    //             ])
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to update roles: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Update user permissions
    //  */
    // public function updatePermissions(Request $request, User $user)
    // {
    //     $request->validate([
    //         'permissions' => 'array',
    //         'permissions.*' => 'string|exists:permissions,name',
    //     ]);

    //     try {
    //         $user->syncPermissions($request->permissions);
            
    //         // Re-fetch user with updated roles/permissions/effective permissions
    //         $user->load('roles.permissions', 'permissions', 'mdas');
    //         $allEffectivePermissions = $user->getAllPermissions()->pluck('name')->toArray();

    //         return response()->json([
    //             'message' => 'Permissions updated successfully',
    //             'user' => array_merge((new UserResource($user))->toArray(request()), [
    //                 'all_permissions' => $allEffectivePermissions,
    //             ])
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to update permissions: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    // public function permissions(User $user)
    // {
    //     // Ensure relations are loaded for UserResource
    //     $userWithRelations = $user->load('roles.permissions', 'permissions'); 

    //     // CRUCIAL: Fetch ALL available roles and permissions
    //     $allRoles = Role::select('id', 'name')->get(); 
    //     $allPermissions = Permission::select('id', 'name')->get();

    //     // Get the user's effective permissions (direct + inherited)
    //     $allEffectivePermissions = $userWithRelations->getAllPermissions()->pluck('name')->toArray();
        
    //     return Inertia::render('admin/users/UserRolesPermissions', [
    //         // Pass the effective permissions list alongside user data
    //         'userData' => array_merge((new UserResource($userWithRelations))->toArray(request()), [
    //             'all_permissions' => $allEffectivePermissions,
    //         ]),
    //         // Pass all available lists to populate the checkboxes
    //         'allRoles' => $allRoles,
    //         'allPermissions' => $allPermissions,
    //     ]);
    // }

    // public function permissions(User $user)
    // {
    //     // Eager load necessary relations
    //     $userWithRelations = $user->load('roles.permissions', 'permissions'); 

    //     // CRUCIAL: Fetch ALL available roles and permissions as simple arrays of strings
    //     $allRoles = Role::all()->pluck('name')->toArray();
    //     $allPermissions = Permission::all()->pluck('name')->toArray();

    //     // dd($allRoles, $allPermissions);

    //     // Get the user's effective permissions (direct + inherited)
    //     $allEffectivePermissions = $userWithRelations->getAllPermissions()->pluck('name')->toArray();
        
    //     return Inertia::render('admin/users/UserRolesPermissions', [
    //         'userData' => array_merge((new UserResource($userWithRelations))->toArray(request()), [
    //             'all_permissions' => $allEffectivePermissions,
    //         ]),
    //         // These two props must contain the simple string arrays
    //         'allRoles' => $allRoles,
    //         'allPermissions' => $allPermissions,
    //     ]);
    // }

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
        $request->validate([
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        try {
            // This syncRoles method handles both assigning (new roles) and removing (missing roles)
            $user->syncRoles($request->roles); 

            // Re-fetch the user to return updated status to the front-end (for index.vue reload)
            $user->load('roles.permissions', 'permissions'); 
            $allEffectivePermissions = $user->getAllPermissions()->pluck('name')->toArray();
            
            return response()->json([
                'message' => 'Roles updated successfully',
                'user' => array_merge((new UserResource($user))->toArray(request()), [
                    'all_permissions' => $allEffectivePermissions,
                ])
            ]);

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
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            // This syncPermissions method handles both assigning and removing
            $user->syncPermissions($request->permissions);
            
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