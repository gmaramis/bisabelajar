<?php

namespace App\Models;

use App\Services\Research\EvidenceValidationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'course_id',
    'activity_id',
    'event_type',
    'payload',
    'occurred_at',
    'session_id',
])]
class LearningEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function validatedEvidence(): HasMany
    {
        return $this->hasMany(ValidatedEvidence::class);
    }

    public static function record(
        string $eventType,
        int $userId,
        int $courseId,
        ?int $activityId = null,
        ?array $payload = null,
        ?string $sessionId = null
    ): self {
        $event = static::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => $eventType,
            'payload' => $payload ?? [],
            'session_id' => $sessionId,
            'occurred_at' => now(),
        ]);

        app(EvidenceValidationService::class)->validateEvent($event);

        return $event;
    }
}
