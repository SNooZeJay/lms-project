{{--
    One account, as a stacked card.

    The Administrator user list uses this layout below the tablet breakpoint, so
    a phone is not given a table it would have to squeeze sideways. Every control
    keeps a 44 pixel target and a visible label.
--}}
<article class="card p-5">
    <div class="flex flex-col gap-2">
        <h3 class="text-base font-semibold text-ink">{{ $user->name }}</h3>
        <p class="break-all text-sm text-ink-muted">{{ $user->email }}</p>

        @unless ($user->hasVerifiedEmail())
            <p class="mt-1"><x-status value="unverified" /></p>
        @endunless
    </div>

    <dl class="mt-4 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2">
        <div>
            <dt class="meta-label">Role</dt>
            <dd class="mt-1">
                <x-status
                    :value="$user->profile->role->value"
                    :label="\App\Support\StatusLabel::words($user->profile->role->value)"
                    tone="primary"
                />
            </dd>
        </div>
        <div>
            <dt class="meta-label">Account status</dt>
            <dd class="mt-1">
                <x-status :value="$user->profile->account_status->value" />
            </dd>
        </div>
    </dl>

    <div class="mt-5 flex flex-col gap-3">
        @if ($user->hasVerifiedEmail())
            <form
                method="POST"
                action="{{ route('admin.users.role.update', $user) }}"
                data-confirm="Apply this role change for {{ $user->name }}?"
                data-pending
            >
                @csrf
                @method('PATCH')

                <label for="mobile-role-{{ $user->id }}" class="field-label">
                    Change role for {{ $user->name }}
                </label>
                <select
                    id="mobile-role-{{ $user->id }}"
                    name="role"
                    class="field-control field-control-surface"
                    @disabled($user->is(request()->user()))
                >
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected($user->profile->role === $roleOption)>
                            {{ \App\Support\StatusLabel::words($roleOption->value) }}
                        </option>
                    @endforeach
                </select>

                <x-btn
                    type="submit"
                    variant="secondary"
                    size="md"
                    block
                    class="mt-3"
                    data-pending-button
                    :disabled="$user->is(request()->user())"
                >
                    <span data-pending-text>Save role</span>
                </x-btn>
            </form>
        @else
            <x-note tone="warning">Role changes unlock after email verification.</x-note>
        @endif

        @if (! $user->is(request()->user()))
            <form
                method="POST"
                action="{{ route('admin.users.status.update', $user) }}"
                data-confirm="{{ $user->profile->account_status->value === 'active' ? 'Suspend' : 'Reactivate' }} this account?"
                data-pending
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
                    size="md"
                    block
                    data-pending-button
                >
                    <span data-pending-text>
                        {{ $user->profile->account_status->value === 'active' ? 'Suspend account' : 'Reactivate account' }}
                    </span>
                </x-btn>
            </form>
        @else
            <x-note tone="neutral">Your own role and account status cannot be changed here.</x-note>
        @endif
    </div>
</article>
