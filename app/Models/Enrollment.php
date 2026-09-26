<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_id');
    }

    /**
     * @param  Builder<Enrollment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Active);
    }

    public function grantsAccess(): bool
    {
        return in_array($this->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true);
    }
}
