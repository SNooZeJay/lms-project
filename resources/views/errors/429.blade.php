@extends('layouts.app')

@section('title', 'Too many requests')

@section('content')
    <x-error-state
        code="429"
        title="Too many requests"
        message="Wait before trying the request again."
    />
@endsection
