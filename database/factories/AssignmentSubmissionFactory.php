<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id' => User::factory(),
            'graded_by' => null,

            /*
             | A path that does not exist on disk.
             |
             | Deliberately. A factory that wrote a real file would make every test
             | that used it touch the filesystem, and the tests that care about the
             | bytes are the download tests, which build their own file. This is a
             | record, and the only thing wrong with a record whose file is missing
             | is that it 404s when read — which is the correct behaviour and is
             | asserted as such.
             */
            'storage_disk' => 'local',
            'storage_path' => 'assignment-submissions/factory/nothing-here.pdf',
            'original_name' => 'my-assignment.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 24576,

            'status' => AssignmentSubmission::PENDING,
            'submitted_at' => now(),
        ];
    }

    public function graded(int $score = 85, int $maximum = 100): static
    {
        return $this->state(fn (): array => [
            'status' => AssignmentSubmission::GRADED,
            'score' => $score,
            'feedback' => 'Clear work, and you explained your reasoning.',
            'graded_by' => User::factory()->instructor(),
            'graded_at' => now(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (): array => [
            'status' => AssignmentSubmission::RETURNED,
            'score' => null,
            'feedback' => 'You described what you did but not why. Add the reasoning.',
            'graded_by' => User::factory()->instructor(),
            'graded_at' => null,
        ]);
    }
}
