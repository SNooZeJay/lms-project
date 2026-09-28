@extends('layouts.app-shell')

@section('title', 'Ask for help')
@section('workspace-context', 'Messages')

@section('content')
    <x-page-header
        title="Ask for help"
        description="Raise a request and an administrator will reply here. Only you and an administrator can read it, so anything you write stays between you and them."
    >
        <x-slot:actions>
            <x-btn :href="route('conversations.index')" variant="quiet" size="md">Back to messages</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('support.store') }}">
            @csrf

            <x-form-errors :errors="$errors" class="mb-4" />

            <input type="hidden" name="client_token" value="{{ (string) Str::uuid() }}" data-client-token>

            <x-form-field
                name="subject"
                label="What is this about"
                required
                :maxlength="160"
                placeholder="A short summary, such as: I cannot open my course"
            />

            {{-- type="textarea", and no child control: x-form-field renders its
                 own control and takes no slot, so a textarea passed as a child
                 is discarded. --}}
            <x-form-field
                name="body"
                label="Tell us what happened"
                type="textarea"
                class="mt-5"
                :rows="6"
                :maxlength="5000"
                required
                hint="What you did, what you expected, and what happened instead."
            />

            <div class="mt-6">
                <x-btn type="submit" variant="primary" size="md">
                    <x-icon name="shield" size="sm" />
                    Send request
                </x-btn>
            </div>
        </form>
    </div>
@endsection
