@extends('layouts.app')

@section('title', 'Edit User')
@section('meta_description', 'Edit a user')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit User</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('users.index') }}">Users</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $user->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('users.update', $user) }}">
            @method('PUT')
            @include('users._form', ['buttonText' => 'Update User'])
        </form>
    </div>
</div>
@endsection
