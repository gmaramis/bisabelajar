<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;

class OwnedContentController extends Controller
{
    /**
     * Placeholder tutor-owned content mutation.
     *
     * Course CRUD belongs to TASK-003. This endpoint enforces the ownership
     * rule that later course management must reuse: a tutor may only mutate
     * content they own.
     */
    public function update(User $owner): Response
    {
        $this->authorize('updateOwnedContent', $owner);

        return response()->noContent();
    }
}
