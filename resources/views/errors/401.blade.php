@extends('layouts.app')

@section('title', 'Sign in required')

@section('content')
    <x-error-state
        code="401"
        icon="lock"
        title="Sign in required"
        message="Sign in to open this page, then return to where you were."
    />
@endsection
