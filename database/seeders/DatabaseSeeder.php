<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The published course catalog. It is the only business data the project
        // ships, because an LMS with an empty catalog cannot be demonstrated or
        // reviewed by anyone. It goes in through the ordinary models, so every
        // seeded course is one an Instructor could have created by hand.
        $this->call(CourseCatalogSeeder::class);
    }
}
