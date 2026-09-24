@extends('layouts.app')

@section('title', 'Something went wrong')

@section('content')
    <x-error-state
        code="500"
        title="Something went wrong"
        message="The server could not complete this request."
    />
@endsection
