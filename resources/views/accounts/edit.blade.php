@extends('layouts')
@section('page_title', 'Edit Account')

@section('content')
    @include('accounts._form', ['account' => $account])
@endsection
