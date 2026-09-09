@extends('layouts.app')

@section('title', 'Members · '.$organization->name)

@section('content')
    <livewire:members-manager :organization="$organization" />
@endsection
