<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\ModuleStatus;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'title', 'description', 'sort_order', 'status'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => ModuleStatus::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ModuleStatus::Published;
    }

    public function canBePublished(): bool
    {
        return $this->course->status === CourseStatus::Published;
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
