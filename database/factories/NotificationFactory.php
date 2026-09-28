<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $type = fake()->randomElement(array_diff(NotificationType::values(), [
            // Platform wide types may not carry a course, and the write seam
            // refuses the pairing, so a factory row has to agree with it.
            NotificationType::Announcement->value,
            NotificationType::SystemAnnouncement->value,
        ]));

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'title' => 'Something happened that you should know about.',
            'body' => fake()->sentence(),
            'course_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'link' => null,
            'dedup_key' => null,
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function forRecipient(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function inCourse(Course $course): static
    {
        return $this->state(fn (): array => ['course_id' => $course->id]);
    }

    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }

    public function withLink(string $link): static
    {
        return $this->state(fn (): array => ['link' => $link]);
    }

    public function withDedupKey(string $key): static
    {
        return $this->state(fn (): array => ['dedup_key' => $key]);
    }
}
