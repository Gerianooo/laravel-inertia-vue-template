<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        Inertia::share([
            'count' => [
                'all' => User::withTrashed()->count(),
                'active' => User::whereNotNull('email_verified_at')->count(),
                'inactive' => User::whereNull('email_verified_at')->count(),
                'deleted' => User::withTrashed()->whereNotNull('deleted_at')->count(),
            ],
        ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return Inertia::render('User/Index')->with([
            'perPage' => $perPage = (int) $request->per_page ?: 10,
            'search' => $search = $request->search,
            'roles' => Role::orderBy('name', 'asc')->get(),
            'permissions' => Permission::orderBy('name', 'asc')->get(),
            'users' => $search ? User::withTrashed()->with(['roles', 'permissions'])->where(function ($query) use ($search) {
                $search = "%$search%";
                $query->where('name', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('email_verified_at', 'like', $search)
                        ->orWhere('created_at', 'like', $search)
                        ->orWhere('deleted_at', 'like', $search);
            })->paginate($perPage) : User::withTrashed()->with(['roles', 'permissions'])->paginate($perPage),
        ]);
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return Inertia::render('User/Create');
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $post = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:64|unique:users',
            'email' => 'required|email|max:255|unique:users',
        ]);

        $post = array_map('mb_strtolower', $post);

        $user = User::create(array_merge($post, [
            'email_verified_at' => now(),
            'password' => Hash::make($password = Str::random(8)),
        ]));

        $context = [
            'id' => $user->id,
        ];

        if ($user) {
            flash(timer: null)->success(__('user has been created with default password ":password"', [
                'password' => $password,
            ]));

            Log::info('creating user', $context);
        } else {
            flash()->error(__("can't create user"));

            Log::error('creating user', $context);
        }

        return redirect(route('superuser.user.index'));
    }

    /**
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        return Inertia::render('User/Edit')->with('user', $user);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $success = $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:64', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]));

        $context = [
            'id' => $user->id,
        ];

        if ($success) {
            flash()->success(__('user has been updated'));

            Log::info('updating user', $context);
        } else {
            flash()->error(__("can't update user"));

            Log::error('updating user', $context);
        }

        return redirect()->back();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, int $id)
    {
        $user = User::withTrashed()->find($id);
        $context = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'permanently' => !empty($request->force),
        ];

        if ($request->force ? $user->forceDelete() : $user->delete()) {
            flash()->success(__('user has been deleted'));

            Log::info('deleting user', $context);
        } else {
            flash()->error(__("can't delete user"));

            Log::error('deleting user', $context);
        }

        return redirect()->back();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function reset(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $context = [
            'id' => $user->id,
            'password' => $password = Str::random(8),
        ];

        if ($user->update([ 'password' => Hash::make($password) ])) {
            flash(timer: null)->success(__('password successfully replaced with ":password"', [
                'password' => $password,
            ]));

            Log::info('updating password', $context);

            if ($request->ajax()) {
                return response()->json([
                    'type' => 'success',
                    'timer' => 5000,
                    'text' => __('password successfully replaced with ":password"', [
                        'password' => $password,
                    ]),
                ]);
            }
        } else {
            flash()->error(__("can't update password"));

            Log::error('updating password', $context);

            if ($request->ajax()) {
                return response()->json([
                    'type' => 'error',
                    'timer' => null,
                    'text' => __("can't update password"),
                ]);
            }
        }

        return redirect()->back();
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function restore(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->deleted_at = null;

        $context = [
            'id' => $id,
        ];

        if ($user->save()) {
            flash()->success(__('user has been restored'));

            Log::info('restoring user', $context);
        } else {
            flash()->error("can't restore user");

            Log::error('restoring user', $context);
        }

        return redirect()->back();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function togglePermission(Request $request)
    {
        $request->validate([
            'userId' => 'required|integer|exists:users,id',
            'permissionId' => 'required|integer|exists:permissions,id',
        ]);

        $user = User::findOrFail($request->userId);
        $permission = Permission::findOrFail($request->permissionId);

        $context = [
            'id' => $user->id,
            'permission' => $permission->name,
        ];

        if ($user->hasPermissionTo($permission->name)) {
            if ($user->revokePermissionTo($permission)) {
                $success = true;
            } else {
                $success = false;
            }
        } else {
            if ($user->givePermissionTo($permission)) {
                $success = true;
            } else {
                $success = false;
            }
        }

        if ($success) {
            Log::info('toggling permission', $context);

            return [
                'type' => 'success',
                'text' => __('permission updated'),
                'timer' => 5000,
            ];
        } else {
            Log::error('toggling permission', $context);

            return [
                'type' => 'error',
                'text' => __('can\'t update permission'),
                'timer' => null,
            ];
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function toggleRole(Request $request)
    {
        $request->validate([
            'userId' => 'required|integer|exists:users,id',
            'roleId' => 'required|integer|exists:roles,id',
        ]);

        $user = User::findOrFail($request->userId);
        $role = Role::findOrFail($request->roleId);

        $context = [
            'id' => $user->id,
            'role' => $role->name,
        ];

        if ($user->hasRole($role->name)) {
            if ($user->removeRole($role)) {
                $success = true;
            } else {
                $success = false;
            }
        } else {
            if ($user->assignRole($role)) {
                $success = true;
            } else {
                $success = false;
            }
        }

        if ($success) {
            Log::info('toggling role', $context);

            return [
                'type' => 'success',
                'text' => __('role updated'),
                'timer' => 5000,
            ];
        } else {
            Log::error('toggling role', $context);

            return [
                'type' => 'error',
                'text' => __('can\'t update role'),
                'timer' => null,
            ];
        }
    }
}
