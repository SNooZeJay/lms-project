@extends('layouts.app')

@section('title', 'Page expired')

@section('content')
    <x-error-state
        code="419"
        icon="clock"
        title="Page expired"
        message="Refresh the page before continuing."
    />
@endsection
