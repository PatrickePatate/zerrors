@extends('layouts.share')

@section('title', $issue->title.' · Zerrors')

@section('content')
    <div class="space-y-6">
        @include('fault.issues._detail', ['isGuest' => true, 'eventNavigation' => null])
    </div>
@endsection
