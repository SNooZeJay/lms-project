@extends('layouts.app')

@section('title', 'Page expired')

@section('content')
    <x-error-state
        code="419"
        title="Page expired"
        message="Refresh the page before continuing."
    />
@endsection
