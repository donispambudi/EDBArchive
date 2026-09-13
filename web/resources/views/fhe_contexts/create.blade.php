@extends('layouts.app')

@section('title', 'Create FHE Context')
@section('meta_description', 'Create an FHE context')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create FHE Context</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-contexts.index') }}">FHE Contexts</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('fhe-contexts.store') }}">
            @include('fhe_contexts._form', ['buttonText' => 'Create Context'])
        </form>
    </div>
</div>
@endsection
