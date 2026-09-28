@extends('layouts.app-shell')

@section('title', 'Manage users')
@section('workspace-context', 'Operations workspace')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Administration"
            title="Manage users"
            description="Search verified accounts, assign one approved role, and manage active access. A suspended account cannot sign in or continue a session."
        >
            <x-slot:actions>
                <x-btn :href="route('admin.activity.index')" variant="secondary" size="md">
                    <x-icon name="activity" size="sm" />
                    View activity
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        {{-- Search and filters. Every control has a visible label, and the form
             keeps a way back to the full list. --}}
        <form
            method="GET"
            action="{{ route('admin.users.index') }}"
            role="search"
            class="card mt-8 grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_11rem_11rem_auto] md:items-end"
        >
            <div>
                <label for="search" class="field-label">Search users</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Name or email"
                    class="field-control field-control-surface"
                >
            </div>

            <div>
                <label for="role" class="field-label">Role</label>
                <select id="role" name="role" class="field-control field-control-surface">
                    <option value="">All roles</option>
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected($selectedRole === $roleOption)>
                            {{ \App\Support\StatusLabel::words($roleOption->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="field-label">Account status</label>
                <select id="status" name="status" class="field-control field-control-surface">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption)>
                            {{ \App\Support\StatusLabel::words($statusOption->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row md:pb-0.5">
                <x-btn type="submit" variant="primary" size="md" class="md:w-auto">
                    <x-icon name="search" size="sm" />
                    Filter
                </x-btn>
                <x-btn :href="route('admin.users.index')" variant="secondary" size="md" class="md:w-auto">
                    Clear
                </x-btn>
            </div>
        </form>

        <p class="mt-6 text-sm text-ink-muted" role="status">
            {{ $users->total() }} {{ Str::plural('account', $users->total()) }} matched
        </p>

        @if ($users->isEmpty())
            <div class="card mt-4">
                <x-empty-state
                    compact
                    icon="users"
                    title="No accounts match"
                    description="Try a different search term, or clear the role and status filters."
                >
                    <x-btn :href="route('admin.users.index')" variant="primary" size="md">
                        Clear filters
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            {{-- Stacked cards on a phone, a table from a tablet up. The table
                 scrolls inside its own container rather than widening the page. --}}
            <div class="mt-6 space-y-4 md:hidden">
                @foreach ($users as $user)
                    @include('admin.users.user-card', ['user' => $user, 'roles' => $roles])
                @endforeach
            </div>

            <div class="card mt-6 hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-4xl text-left text-sm">
                        <caption class="sr-only">
                            One row per account, with the email, role, account status, creation date, and the
                            available actions
                        </caption>
                        <thead class="table-head">
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col">Role</th>
                                <th scope="col">Account status</th>
                                <th scope="col">Created</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody role="list" class="divide-y divide-line">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="table-cell">
                                        <p class="font-semibold text-ink">{{ $user->name }}</p>
                                        <p class="mt-1 break-all text-ink-muted">{{ $user->email }}</p>
                                        @unless ($user->hasVerifiedEmail())
                                            <p class="mt-2">
                                                <x-status value="unverified" />
                                            </p>
                                        @endunless
                                    </td>
                                    <td class="table-cell">
                                        <x-status
                                            :value="$user->profile->role->value"
                                            :label="\App\Support\StatusLabel::words($user->profile->role->value)"
                                            tone="primary"
                                        />
                                    </td>
                                    <td class="table-cell">
                                        <x-status :value="$user->profile->account_status->value" />
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-ink-muted">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex min-w-72 flex-col gap-3">
                                            @if ($user->hasVerifiedEmail())
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.users.role.update', $user) }}"
                                                    data-confirm="Apply this role change for {{ $user->name }}?"
                                                    class="flex items-end gap-2"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="min-w-0 flex-1">
                                                        <label for="role-{{ $user->id }}" class="sr-only">
                                                            Role for {{ $user->name }}
                                                        </label>
                                                        <select
                                                            id="role-{{ $user->id }}"
                                                            name="role"
                                                            class="field-control field-control-surface"
                                                            @disabled($user->is(request()->user()))
                                                        >
                                                            @foreach ($roles as $roleOption)
                                                                <option
                                                                    value="{{ $roleOption->value }}"
                                                                    @selected($user->profile->role === $roleOption)
                                                                >{{ \App\Support\StatusLabel::words($roleOption->value) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <x-btn
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        data-pending-button
                                                        :disabled="$user->is(request()->user())"
                                                    >
                                                        <span data-pending-text>Save role</span>
                                                    </x-btn>
                                                </form>
                                            @else
                                                <x-note tone="warning">
                                                    Role changes unlock after email verification.
                                                </x-note>
                                            @endif

                                            @if (! $user->is(request()->user()))
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.users.status.update', $user) }}"
                                                    data-confirm="{{ $user->profile->account_status->value === 'active' ? 'Suspend' : 'Reactivate' }} this account?"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="{{ $user->profile->account_status->value === 'active' ? 'suspended' : 'active' }}"
                                                    >
                                                    <x-btn
                                                        type="submit"
                                                        :variant="$user->profile->account_status->value === 'active' ? 'danger' : 'secondary'"
                                                        size="sm"
                                                        data-pending-button
                                                    >
                                                        <span data-pending-text>
                                                            {{ $user->profile->account_status->value === 'active' ? 'Suspend account' : 'Reactivate account' }}
                                                        </span>
                                                    </x-btn>
                                                </form>
                                            @else
                                                <x-note tone="neutral">
                                                    Your own role and account status cannot be changed here.
                                                </x-note>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
