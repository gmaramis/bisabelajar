<?php

namespace App\Models;

use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use Database\Factories\LearningUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Atomic learning container inside a Module.
 *
 * Later milestones may associate materials, activities, evidence,
 * mastery rules, objectives, and prerequisites. Those engines are out of M1 TASK-005.
 */
#[Fillable(['module_id', 'title', 'slug', 'description', 'sort_order', 'status'])]
class LearningUnit extends Model
{
    /** @use HasFactory<LearningUnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => LearningUnitStatus::class,
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function isPublished(): bool
    {
        return $this->status === LearningUnitStatus::Published;
    }

    public function canBePublished(): bool
    {
        return $this->module->status === ModuleStatus::Published
            && $this->module->canBePublished();
    }

    public function canBeDeleted(): bool
    {
        return true;
    }

    public static function uniqueSlug(int $moduleId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'unit';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('module_id', $moduleId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
