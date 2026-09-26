@extends('layouts.app')

@section('title', 'Page not found')

@section('content')
    <x-error-state
        code="404"
        icon="search"
        title="Page not found"
        message="The page may have moved or may not exist."
    />
@endsection
