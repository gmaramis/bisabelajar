<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function showUser(Request $request, User $user): View
    {
        abort_unless($request->user()?->is($user), 403);

        return view('profile.show', [
            'user' => $user,
        ]);
    }
}
