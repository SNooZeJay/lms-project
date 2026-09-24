@extends('layouts.app')

@section('title', 'Sign in required')

@section('content')
    <x-error-state
        code="401"
        title="Sign in required"
        message="Open the home page and continue from a supported application flow."
    />
@endsection
