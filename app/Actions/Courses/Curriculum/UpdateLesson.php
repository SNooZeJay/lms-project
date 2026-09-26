<?php

namespace App\Actions\Courses\Curriculum;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateLesson
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Lesson $lesson, array $data): Lesson
    {
        Gate::forUser($actor)->authorize('update', $lesson);

        return DB::transaction(function () use ($lesson, $data): Lesson {
            $lesson->forceFill([
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'content_text' => $data['content_text'] ?? null,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
            ]);
            $lesson->save();

            return $lesson;
        });
    }
}
