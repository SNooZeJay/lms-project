<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-course completion rules. Created on demand with safe defaults.
 */
class CourseRequirement extends Model
{
    protected $primaryKey = 'course_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'require_all_lessons',
        'minimum_lesson_percent',
        'require_required_quizzes',
        'require_passing_score',
        'certificate_enabled',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'require_all_lessons' => 'boolean',
            'require_required_quizzes' => 'boolean',
            'require_passing_score' => 'boolean',
            'certificate_enabled' => 'boolean',
            'minimum_lesson_percent' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
