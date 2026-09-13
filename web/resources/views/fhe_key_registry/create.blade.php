@extends('layouts.app')

@section('title', 'Create FHE Key')
@section('meta_description', 'Create FHE key registry metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create FHE Key</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-key-registry.index') }}">FHE Key Registry</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('fhe-key-registry.store') }}">
            @include('fhe_key_registry._form', ['buttonText' => 'Create Key'])
        </form>
    </div>
</div>
@endsection
