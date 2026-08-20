<?php

namespace App\Models;

use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use Database\Factories\LearningMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Learning material attached to a Learning Unit.
 *
 * Opening or viewing material does not record mastery or progress.
 * Additional material types can be added via MaterialType.
 */
#[Fillable([
    'learning_unit_id',
    'title',
    'type',
    'content',
    'external_url',
    'file_path',
    'disk',
    'mime_type',
    'sort_order',
    'status',
])]
class LearningMaterial extends Model
{
    /** @use HasFactory<LearningMaterialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'status' => MaterialStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function learningUnit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class);
    }

    public function isPublished(): bool
    {
        return $this->status === MaterialStatus::Published;
    }

    public function isFile(): bool
    {
        return in_array($this->type, [MaterialType::Pdf, MaterialType::Powerpoint], true);
    }

    public function canBePublished(): bool
    {
        return $this->learningUnit->isPublished()
            && $this->learningUnit->canBePublished();
    }

    public function downloadName(): string
    {
        $base = Str::slug($this->title) ?: 'material';
        $extension = pathinfo((string) $this->file_path, PATHINFO_EXTENSION);

        return $extension === '' ? $base : $base.'.'.$extension;
    }

    public function deleteStoredFile(): void
    {
        if ($this->disk && $this->file_path) {
            Storage::disk($this->disk)->delete($this->file_path);
        }
    }
}
