@extends('layouts.app')

@section('title', 'Manage users')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="font-mono text-sm font-semibold text-primary-text">Administration</p>
                <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Manage users</h1>
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Search verified accounts, assign one approved role, and manage active access.</p>
            </div>
            <a href="{{ route('admin.activity.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View activity</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        <form method="GET" action="{{ route('admin.users.index') }}" class="mt-8 grid gap-4 border-y border-line py-6 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto] md:items-end">
            <div>
                <label for="search" class="block text-sm font-semibold text-ink">Search users</label>
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name or email" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>
            <div>
                <label for="role" class="block text-sm font-semibold text-ink">Role</label>
                <select id="role" name="role" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <option value="">All roles</option>
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected($selectedRole === $roleOption)>{{ ucfirst($roleOption->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-semibold text-ink">Status</label>
                <select id="status" name="status" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption)>{{ ucfirst($statusOption->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Filter</button>
        </form>

        @if ($users->isEmpty())
            <div class="border-t border-line py-12 text-center">
                <h2 class="text-lg font-semibold text-ink">No users found</h2>
                <p class="mt-2 text-sm text-ink-muted">Try a different search or filter.</p>
            </div>
        @else
            <div class="mt-8 space-y-4 md:hidden">
                @foreach ($users as $user)
                    @include('admin.users.user-card', ['user' => $user, 'roles' => $roles])
                @endforeach
            </div>

            <div class="mt-8 hidden overflow-x-auto border-t border-line md:block">
                <table class="min-w-full divide-y divide-line text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">User</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Created</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-5 align-top">
                                    <p class="font-semibold text-ink">{{ $user->name }}</p>
                                    <p class="mt-1 break-all text-ink-muted">{{ $user->email }}</p>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <span class="font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</span>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->profile->account_status->value === 'active' ? 'bg-success-surface text-success-text' : 'bg-error-surface text-error-text' }}">{{ ucfirst($user->profile->account_status->value) }}</span>
                                </td>
                                <td class="px-4 py-5 align-top text-ink-muted">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-5 align-top">
                                    <div class="flex min-w-64 flex-col gap-3">
                                        <form method="POST" action="{{ route('admin.users.role.update', $user) }}" data-confirm="Apply this role change for {{ $user->name }}?" class="flex items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <div class="min-w-0 flex-1">
                                                <label for="role-{{ $user->id }}" class="sr-only">Role for {{ $user->name }}</label>
                                                <select id="role-{{ $user->id }}" name="role" class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus" @disabled($user->is(request()->user()))>
                                                    @foreach ($roles as $roleOption)
                                                        <option value="{{ $roleOption->value }}" @selected($user->profile->role === $roleOption)>{{ ucfirst($roleOption->value) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="min-h-11 rounded-md border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus" @disabled($user->is(request()->user()))>Save role</button>
                                        </form>
                                        @if (! $user->is(request()->user()))
                                            <form method="POST" action="{{ route('admin.users.status.update', $user) }}" data-confirm="{{ $user->profile->account_status->value === 'active' ? 'Suspend' : 'Reactivate' }} this account?">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $user->profile->account_status->value === 'active' ? 'suspended' : 'active' }}">
                                                <button type="submit" class="min-h-11 rounded-md border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                                                    {{ $user->profile->account_status->value === 'active' ? 'Suspend account' : 'Reactivate account' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
