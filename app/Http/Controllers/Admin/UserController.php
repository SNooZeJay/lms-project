<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Authentication\AssignUserRole;
use App\Actions\Authentication\UpdateAccountStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountStatusRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->string('search'));
        $role = UserRole::tryFrom((string) $request->string('role'));
        $status = UserAccountStatus::tryFrom((string) $request->string('status'));

        $users = User::query()
            ->with('profile')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, fn ($query) => $query->whereHas('profile', fn ($profileQuery) => $profileQuery->where('role', $role->value)))
            ->when($status, fn ($query) => $query->whereHas('profile', fn ($profileQuery) => $profileQuery->where('account_status', $status->value)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'selectedRole' => $role,
            'selectedStatus' => $status,
            'roles' => UserRole::cases(),
            'statuses' => UserAccountStatus::cases(),
        ]);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        AssignUserRole $assignUserRole,
    ): RedirectResponse {
        Gate::authorize('updateRole', $user);
        $assignUserRole->handle($request->user(), $user, UserRole::from($request->validated('role')));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Role updated.');
    }

    public function updateStatus(
        UpdateAccountStatusRequest $request,
        User $user,
        UpdateAccountStatus $updateAccountStatus,
    ): RedirectResponse {
        Gate::authorize('updateStatus', $user);
        $updateAccountStatus->handle($request->user(), $user, UserAccountStatus::from($request->validated('status')));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Account status updated.');
    }
}
