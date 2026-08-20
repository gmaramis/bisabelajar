<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class WorkspaceController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('tutor.courses.index');
    }
}
