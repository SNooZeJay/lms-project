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

        /*
         | The three accounts, and the one that was missing.
         |
         | Only the catalog was seeded, so a machine that followed the documented
         | setup ended up with five courses and no way to demonstrate the student
         | or the administrator side of the product. Two thirds of an LMS cannot
         | be shown by anybody who set it up from the repository.
         |
         | It runs after the catalog because the catalog's instructor is one of
         | the accounts, and creating it first would let the seeder adopt a
         | half made profile.
         */
        $this->call(DemoAccountsSeeder::class);
    }
}
