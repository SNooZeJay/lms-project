<?php

namespace App\Actions\Courses\Curriculum;

use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateModule
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Module $module, array $data): Module
    {
        Gate::forUser($actor)->authorize('update', $module);

        return DB::transaction(function () use ($module, $data): Module {
            $module->forceFill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);
            $module->save();

            return $module;
        });
    }
}
