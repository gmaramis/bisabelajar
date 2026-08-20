<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['owner_id', 'title', 'slug', 'description', 'thumbnail', 'status', 'visibility'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'visibility' => CourseVisibility::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->latest('enrolled_at');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    public function isLearnableBy(?User $user): bool
    {
        if ($user === null || ! $user->isStudent() || ! $this->isPublished()) {
            return false;
        }

        return $this->enrollments()
            ->where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Active)
            ->exists();
    }

    public function isEnrollable(): bool
    {
        return $this->isPubliclyViewable();
    }

    public function isEnrolledBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->enrollments()->where('user_id', $user->id)->exists();
    }

    public function isDiscoverable(): bool
    {
        return $this->status === CourseStatus::Published
            && $this->visibility === CourseVisibility::Public;
    }

    public function isPubliclyViewable(): bool
    {
        return $this->status === CourseStatus::Published
            && in_array($this->visibility, [CourseVisibility::Public, CourseVisibility::Unlisted], true);
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query
            ->where('status', CourseStatus::Published)
            ->where('visibility', CourseVisibility::Public);
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'course';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
