@extends('layouts.app')

@section('title', 'Profile — '.config('app.name'))

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-xl font-semibold">Profile</h1>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="font-medium text-slate-500">Name</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-500">Email</dt>
                <dd>{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-500">Role</dt>
                <dd>{{ strtoupper($user->role->value) }}</dd>
            </div>
        </dl>
    </div>
@endsection
