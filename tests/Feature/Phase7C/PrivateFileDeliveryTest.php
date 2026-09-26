<?php

namespace Tests\Feature\Phase7C;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\LearningMaterialType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Real content so the server-side MIME check sees a genuine file, exactly
     * as it would in production.
     */
    private function pdfFile(string $name = 'handout.pdf', int $kilobytes = 120): UploadedFile
    {
        $body = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, str_pad($body, $kilobytes * 1024, ' '));
    }

    public function test_instructor_can_upload_a_pdf_material(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'Lab handout',
                'material_type' => LearningMaterialType::Pdf->value,
                'file' => $this->pdfFile(),
            ])
            ->assertRedirect($this->courseShowUrl($course));

        $material = LearningMaterial::query()->firstOrFail();

        $this->assertSame('Lab handout', $material->title);
        $this->assertSame(LearningMaterialType::Pdf, $material->material_type);
        $this->assertSame('local', $material->storage_disk);
        $this->assertNotNull($material->storage_path);
        $this->assertSame('application/pdf', $material->mime_type);
        $this->assertGreaterThan(0, $material->byte_size);
        $this->assertSame($instructor->id, $material->uploaded_by);

        Storage::disk('local')->assertExists($material->storage_path);
    }

    public function test_the_stored_path_never_contains_the_original_filename(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'Secret handout',
                'material_type' => LearningMaterialType::Pdf->value,
                'file' => $this->pdfFile('secret handout.pdf', 40),
            ]);

        $material = LearningMaterial::query()->firstOrFail();

        $this->assertStringNotContainsString('secret handout', $material->storage_path);
        $this->assertStringNotContainsString(' ', $material->storage_path);
        $this->assertStringStartsWith('learning-materials/', $material->storage_path);
    }

    public function test_two_uploads_of_the_same_name_get_different_paths(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        foreach (['a.pdf', 'b.pdf'] as $name) {
            $this->actingAs($instructor)
                ->post($this->materialStoreUrl($course, $module, $lesson), [
                    'title' => 'Handout '.$name,
                    'material_type' => LearningMaterialType::Pdf->value,
                    'file' => $this->pdfFile($name, 30),
                ]);
        }

        $paths = LearningMaterial::query()->pluck('storage_path')->all();

        $this->assertCount(2, $paths);
        $this->assertCount(2, array_unique($paths));
    }

    public function test_executable_and_script_uploads_are_rejected(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        foreach (['payload.exe', 'payload.php', 'payload.html', 'payload.svg', 'payload.js'] as $name) {
            $this->actingAs($instructor)
                ->from($this->courseShowUrl($course))
                ->post($this->materialStoreUrl($course, $module, $lesson), [
                    'title' => 'Bad file',
                    'material_type' => LearningMaterialType::Document->value,
                    'file' => UploadedFile::fake()->create($name, 10, 'application/octet-stream'),
                ])
                ->assertSessionHasErrors('file');
        }

        $this->assertSame(0, LearningMaterial::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_a_file_type_needs_a_file_and_a_text_type_must_not_carry_one(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'No file',
                'material_type' => LearningMaterialType::Pdf->value,
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'Text with a file',
                'material_type' => LearningMaterialType::Text->value,
                'content_text' => 'Body',
                'file' => $this->pdfFile('notes.pdf', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_upload_rejects_injected_storage_fields(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'Handout',
                'material_type' => LearningMaterialType::Pdf->value,
                'file' => $this->pdfFile('handout.pdf', 25),
                'storage_disk' => 'public',
                'storage_path' => '../../evil.pdf',
                'mime_type' => 'text/html',
                'byte_size' => 999999,
                'uploaded_by' => 999999,
                'position' => 99,
            ])
            ->assertSessionHasErrors(['storage_disk', 'storage_path', 'mime_type', 'byte_size', 'uploaded_by', 'position']);

        $this->assertSame(0, LearningMaterial::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_a_clean_upload_writes_only_server_owned_storage_values(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->post($this->materialStoreUrl($course, $module, $lesson), [
                'title' => 'Handout',
                'material_type' => LearningMaterialType::Pdf->value,
                'file' => $this->pdfFile('handout.pdf', 25),
            ])
            ->assertRedirect();

        $material = LearningMaterial::query()->firstOrFail();

        $this->assertSame('local', $material->storage_disk);
        $this->assertStringNotContainsString('..', $material->storage_path);
        $this->assertSame('application/pdf', $material->mime_type);
        $this->assertSame($instructor->id, $material->uploaded_by);
        $this->assertSame(1, $material->position);
        $this->assertGreaterThan(0, $material->byte_size);
    }

    public function test_enrolled_student_can_download_a_file_material(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="lab-handout.pdf"');
    }

    public function test_student_without_enrollment_cannot_download(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertForbidden();
    }

    public function test_instructor_cannot_download_from_another_course(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $stranger = User::factory()->instructor()->create();

        $this->actingAs($stranger)
            ->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertForbidden();
    }

    public function test_administrator_can_download_any_material(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)
            ->get("/admin/materials/{$material->id}/download")
            ->assertOk();
    }

    public function test_owning_instructor_can_download(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');

        $this->actingAs($instructor)
            ->get($this->instructorDownloadUrl($course, $module, $lesson, $material))
            ->assertOk();
    }

    public function test_guest_cannot_download(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');

        $this->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertRedirect(route('login'));
    }

    public function test_download_of_a_material_without_a_file_is_not_found(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $text = LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'material_type' => LearningMaterialType::Text,
            'content_text' => 'Body',
        ]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->get($this->studentDownloadUrl($course, $lesson, $text))
            ->assertNotFound();
    }

    public function test_download_returns_not_found_for_a_material_from_another_lesson(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $otherLesson = Lesson::factory()->for($module, 'module')->create([
            'position' => 2,
            'status' => ContentStatus::Published,
        ]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->get($this->studentDownloadUrl($course, $otherLesson, $material))
            ->assertNotFound();
    }

    public function test_download_never_renders_inline(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $student = $this->enrolledStudent($course);

        $response = $this->actingAs($student)
            ->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_download_filename_is_derived_from_the_title_and_extension(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Week 1 Lab Handout');
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->get($this->studentDownloadUrl($course, $lesson, $material))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="week-1-lab-handout.pdf"');
    }

    public function test_lesson_page_offers_a_download_for_a_file_material(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $this->uploadedPdf($lesson, $instructor, 'Lab handout');
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}/lessons/{$lesson->id}")
            ->assertOk()
            ->assertSee('Download')
            ->assertDontSee('not available yet');
    }

    public function test_no_public_route_serves_a_storage_path(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $material = $this->uploadedPdf($lesson, $instructor, 'Lab handout');

        // Whatever the web server answers, the bytes must never come back.
        foreach ([
            "/storage/{$material->storage_path}",
            "/build/../{$material->storage_path}",
            "/{$material->storage_path}",
        ] as $url) {
            $response = $this->get($url);

            $this->assertNotSame(200, $response->getStatusCode(), "{$url} returned the file.");
            $this->assertStringNotContainsString('pdf bytes', $response->getContent() ?? '');
        }
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson}
     */
    private function publishedCourse(): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        return [$instructor, $course, $module, $lesson];
    }

    private function uploadedPdf(Lesson $lesson, User $uploader, string $title): LearningMaterial
    {
        $path = 'learning-materials/'.$lesson->id.'/manual.pdf';
        Storage::disk('local')->put($path, 'pdf bytes');

        $material = new LearningMaterial;
        $material->forceFill([
            'lesson_id' => $lesson->id,
            'uploaded_by' => $uploader->id,
            'title' => $title,
            'material_type' => LearningMaterialType::Pdf,
            'position' => 1,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'byte_size' => 9,
        ]);
        $material->save();

        return $material;
    }

    private function enrolledStudent(Course $course): User
    {
        $student = User::factory()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return $student;
    }

    private function courseShowUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}";
    }

    private function materialStoreUrl(Course $course, Module $module, Lesson $lesson): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/materials";
    }

    private function studentDownloadUrl(Course $course, Lesson $lesson, LearningMaterial $material): string
    {
        return "/student/courses/{$course->id}/lessons/{$lesson->id}/materials/{$material->id}/download";
    }

    private function instructorDownloadUrl(
        Course $course,
        Module $module,
        Lesson $lesson,
        LearningMaterial $material,
    ): string {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/materials/{$material->id}/download";
    }
}
