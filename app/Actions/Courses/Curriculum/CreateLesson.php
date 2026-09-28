<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\ContentStatus;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Support\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateLesson
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Module $module, array $data): Lesson
    {
        Gate::forUser($actor)->authorize('create', [Lesson::class, $module]);

        return DB::transaction(function () use ($module, $data): Lesson {
            $lesson = new Lesson;
            $lesson->forceFill([
                'module_id' => $module->id,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($module, $data['title']),
                'summary' => $data['summary'] ?? null,
                'content_text' => $data['content_text'] ?? null,
                'position' => Position::reserve($module, $module->lessons()),
                'status' => ContentStatus::Draft,
                'is_required' => (bool) ($data['is_required'] ?? true),
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
            ]);
            $lesson->save();

            return $lesson;
        });
    }

    private function uniqueSlug(Module $module, string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'lesson';
        $slug = $baseSlug;
        $suffix = 2;

        while ($module->lessons()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
