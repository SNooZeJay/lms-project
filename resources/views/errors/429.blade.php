@extends('layouts.app')

@section('title', 'Too many requests')

@section('content')
    {{--
        A temporary refusal, so it has to say two useful things: that waiting
        will work, and roughly how long. A bare "too many requests" leaves a
        person deciding between refreshing immediately, which fails again, and
        giving up on the page entirely.

        The wait comes from the limiter rather than being guessed, so the number
        on the page is the number the server is actually enforcing.
    --}}
    <x-error-state
        code="429"
        icon="clock"
        title="Too many requests"
        :message="isset($retryAfter) && $retryAfter > 0
            ? 'That was more requests than this account allows in a minute. Wait about '.max(1, (int) ceil($retryAfter / 60)).' minute, then try again.'
            : 'That was more requests than this account allows in a minute. Wait a moment, then try again.'"
    />
@endsection
