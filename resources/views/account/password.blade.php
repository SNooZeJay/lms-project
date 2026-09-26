@extends('layouts.app-shell')

@section('title', 'Change your password')
@section('workspace-context', 'Account')

@section('content')
    <div class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Account security"
            title="Change your password"
            description="Use a new password before opening the rest of your account."
        />

        <section class="card-accent-edge mt-8 p-5 sm:p-6" aria-labelledby="password-heading">
            <h2 id="password-heading" class="text-base font-semibold text-ink">New password</h2>

            @if (session('status'))
                <x-note tone="success" class="mt-5">{{ session('status') }}</x-note>
            @endif

            <x-form-errors :errors="$errors" class="mt-5" />

            <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-5" data-pending>
                @csrf

                <x-form-field
                    name="current_password"
                    label="Current password"
                    type="password"
                    :autocomplete="'current-password'"
                    hint="Enter the password you use now."
                    required
                    autofocus
                />

                <x-form-field
                    name="password"
                    label="New password"
                    type="password"
                    :autocomplete="'new-password'"
                    hint="Use at least 12 characters. A short phrase of a few words works well."
                    required
                />

                <x-form-field
                    name="password_confirmation"
                    label="Confirm new password"
                    type="password"
                    :autocomplete="'new-password'"
                    hint="Type the same password again."
                    required
                />

                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Change password</span>
                </x-btn>
            </form>
        </section>

        <div class="mt-6 border-t border-line pt-6">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-btn type="submit" variant="secondary" size="md">
                    <x-icon name="log-out" size="sm" />
                    Sign out instead
                </x-btn>
            </form>
        </div>
    </div>
@endsection
