<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateModule
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Course $course, array $data): Module
    {
        Gate::forUser($actor)->authorize('create', [Module::class, $course]);

        return DB::transaction(function () use ($course, $data): Module {
            $module = new Module;
            $module->forceFill([
                'course_id' => $course->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'position' => ((int) $course->modules()->max('position')) + 1,
                'status' => ContentStatus::Draft,
            ]);
            $module->save();

            return $module;
        });
    }
}
