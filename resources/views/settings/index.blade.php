@extends('layouts.app')

@section('title', 'Settings')
@section('subtitle', 'Application, queue, scheduler, timezone, and provider configuration')

@section('content')
<div class="row g-4">
    @foreach(['Facebook','Instagram','TikTok','Queue','Scheduler','Timezone','Application','Media'] as $section)
        <div class="col-md-6 col-xl-3">
            <div class="panel settings-tile">
                <i class="bi bi-sliders"></i>
                <h2>{{ $section }}</h2>
                <p>Configuration surface reserved for internal administrators.</p>
            </div>
        </div>
    @endforeach
</div>
@endsection
