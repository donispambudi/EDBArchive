@extends('layouts.app')

@section('title', 'Create Scheme')
@section('meta_description', 'Create a scheme')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create Scheme</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('schemes.index') }}">Schemes</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('schemes.store') }}">
            @include('schemes._form', ['buttonText' => 'Create Scheme'])
        </form>
    </div>
</div>
@endsection
