@extends('layouts.app')

@section('title', 'Profile — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8 max-w-3xl mx-auto">
    <x-page-header 
        title="Profile" 
        description="Informasi akun pengguna Anda pada platform BisaBelajar."
    />

    <x-card>
        <x-description-list>
            <x-description-item label="Name" :value="$user->name" class="font-bold" />
            <x-description-item label="Email" :value="$user->email" />
            <x-description-item label="Role">
                <x-badge variant="primary">{{ strtoupper($user->role->value) }}</x-badge>
            </x-description-item>
        </x-description-list>
    </x-card>
</div>
@endsection
