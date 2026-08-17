@extends('layouts')
@section('page_title', 'Create Account')

@section('content')
    @include('accounts._form', ['account' => null])
@endsection
