@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
    <x-error-state
        code="403"
        icon="shield"
        title="Access denied"
        message="You do not have permission to open this page."
    />
@endsection
