<article class="border border-line bg-surface p-5 shadow-sm">
    <div class="flex flex-col gap-2">
        <h3 class="font-semibold text-ink">{{ $user->name }}</h3>
        <p class="break-all text-sm text-ink-muted">{{ $user->email }}</p>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-ink-muted">Role</dt>
            <dd class="mt-1 font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd>
        </div>
        <div>
            <dt class="text-ink-muted">Status</dt>
            <dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->profile->account_status->value === 'active' ? 'bg-success-surface text-success-text' : 'bg-error-surface text-error-text' }}">{{ ucfirst($user->profile->account_status->value) }}</span></dd>
        </div>
    </dl>

    <div class="mt-5 flex flex-col gap-3">
        <form method="POST" action="{{ route('admin.users.role.update', $user) }}" data-confirm="Apply this role change for {{ $user->name }}?" class="flex flex-col gap-2 sm:flex-row">
            @csrf
            @method('PATCH')
            <div class="min-w-0 flex-1">
                <label for="mobile-role-{{ $user->id }}" class="sr-only">Role for {{ $user->name }}</label>
                <select id="mobile-role-{{ $user->id }}" name="role" class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus" @disabled($user->is(request()->user()))>
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
                <button type="submit" class="min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    {{ $user->profile->account_status->value === 'active' ? 'Suspend account' : 'Reactivate account' }}
                </button>
            </form>
        @endif
    </div>
</article>
