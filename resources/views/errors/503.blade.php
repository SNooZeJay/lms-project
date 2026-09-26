@extends('layouts.app')

@section('title', 'Temporarily unavailable')

@section('content')
    <x-error-state
        code="503"
        icon="alert"
        title="Temporarily unavailable"
        message="The application is undergoing maintenance. Try again later."
    />
@endsection
