@extends('layouts.app-shell')

@section('title', 'Your profile')
@section('workspace-context', 'Account')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Account"
            title="Your profile"
            description="Keep the name and short biography shown on your learning account."
        >
            <x-slot:actions>
                <x-btn :href="route('account.password')" variant="secondary" size="md">
                    <x-icon name="lock" size="sm" />
                    Change password
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <section class="card p-5 sm:p-6" aria-labelledby="profile-form-heading">
                <h2 id="profile-form-heading" class="text-base font-semibold text-ink">Profile details</h2>

                <form
                    method="POST"
                    action="{{ route('account.profile.update') }}"
                    class="mt-6 space-y-6"
                    data-pending
                >
                    @csrf
                    @method('PATCH')

                    <x-form-field
                        name="name"
                        label="Display name"
                        :value="$user->name"
                        :autocomplete="'name'"
                        :maxlength="255"
                        hint="This is the name other people see on your courses and certificates."
                        required
                    />

                    <x-form-field
                        name="bio"
                        label="Short biography"
                        type="textarea"
                        :rows="5"
                        :value="$user->profile->bio"
                        :maxlength="500"
                        hint="Optional, up to 500 characters."
                    />

                    <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                        <span data-pending-text>Save profile</span>
                    </x-btn>
                </form>
            </section>

            {{-- The facts a person cannot change here. Saying so plainly is
                 kinder than a disabled field with no explanation. --}}
            <aside class="h-fit card-muted p-5" aria-labelledby="account-details-heading">
                <h2 id="account-details-heading" class="text-sm font-semibold text-ink">Account details</h2>

                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="meta-label">Email</dt>
                        <dd class="meta-value">{{ $user->email }}</dd>
                    </div>
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
                    <div>
                        <dt class="meta-label">Email confirmation</dt>
                        <dd class="mt-1">
                            <x-status :value="$user->hasVerifiedEmail() ? 'verified' : 'unverified'" />
                        </dd>
                    </div>
                </dl>

                <x-note tone="neutral" class="mt-5">
                    Your email address, role, and account status are not changed from this form. Only an
                    Administrator can change a role or an account status.
                </x-note>
            </aside>
        </div>
    </div>
@endsection
