@extends('layouts.app')

@section('content')
    <div class="mx-auto flex min-h-[34rem] w-full max-w-2xl items-center px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <div class="w-full">
            <a href="{{ route('home') }}" class="mb-6 inline-flex min-h-11 items-center text-sm font-semibold text-primary-text underline decoration-primary-text/40 underline-offset-4 hover:decoration-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                <span aria-hidden="true" class="mr-2">←</span>
                Back to home
            </a>

            @yield('auth-content')
        </div>
    </div>
@endsection
