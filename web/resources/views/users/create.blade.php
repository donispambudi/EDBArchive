@extends('layouts.app')

@section('title', 'Create User')
@section('meta_description', 'Create a user')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create User</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('users.index') }}">Users</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('users.store') }}">
            @include('users._form', ['buttonText' => 'Create User'])
        </form>
    </div>
</div>
@endsection
