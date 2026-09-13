@extends('layouts.app')

@section('title', 'Edit Share')
@section('meta_description', 'Edit share/export metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Share</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('shares.index') }}">Shares</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">#{{ $share->id }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('shares.update', $share) }}">
            @method('PUT')
            @include('shares._form', ['buttonText' => 'Update Share'])
        </form>
    </div>
</div>
@endsection
