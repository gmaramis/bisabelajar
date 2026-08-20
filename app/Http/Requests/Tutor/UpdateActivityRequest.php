<?php

namespace App\Http\Requests\Tutor;

use App\Models\Activity;

class UpdateActivityRequest extends StoreActivityRequest
{
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            && ($this->user()?->can('update', $activity) ?? false);
    }
}
