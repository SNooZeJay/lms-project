@extends('layouts.app')

@section('title', 'Your profile')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="mb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">Account</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-ink">Your profile</h1>
            <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Keep the name and short biography shown on your learning account.</p>
        </div>

        @if (session('status'))
            <div role="status" class="mb-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[1fr_18rem]">
            <section class="border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-8" aria-labelledby="profile-form-heading">
                <h2 id="profile-form-heading" class="text-lg font-semibold text-ink">Profile details</h2>

                <form method="POST" action="{{ route('account.profile.update') }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="name" class="block text-sm font-semibold text-ink">Display name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                        @error('name')
                            <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="bio" class="block text-sm font-semibold text-ink">Short biography</label>
                        <textarea id="bio" name="bio" rows="5" maxlength="500" class="mt-2 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('bio', $user->profile->bio) }}</textarea>
                        <p class="mt-2 text-sm text-ink-muted">Optional, up to 500 characters.</p>
                        @error('bio')
                            <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        Save profile
                    </button>
                </form>
            </section>

            <aside class="h-fit border-t-4 border-line bg-surface-muted p-6" aria-labelledby="account-details-heading">
                <h2 id="account-details-heading" class="text-sm font-semibold text-ink">Account details</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-ink-muted">Email</dt>
                        <dd class="mt-1 break-words font-medium text-ink">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Role</dt>
                        <dd class="mt-1 font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Status</dt>
                        <dd class="mt-1 font-medium text-ink">{{ ucfirst($user->profile->account_status->value) }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </div>
@endsection
