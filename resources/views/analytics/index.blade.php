@extends('layouts.app')

@section('title', 'Analytics')
@section('subtitle', 'Publishing volume, platform mix, success rate, and failures')

@section('content')
<div class="row g-4">
    @foreach(['Posts Per Platform','Posts Per Month','Most Active Platform','Most Active Page','Posting Success Rate','Failed Posts','Upcoming Posts'] as $metric)
        <div class="col-md-6 col-xl-4">
            <div class="panel settings-tile">
                <i class="bi bi-graph-up"></i>
                <h2>{{ $metric }}</h2>
                <p>Analytics query surface reserved for the next reporting phase.</p>
            </div>
        </div>
    @endforeach
</div>
@endsection
