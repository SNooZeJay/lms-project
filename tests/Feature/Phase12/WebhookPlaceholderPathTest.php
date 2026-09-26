<?php

namespace Tests\Feature\Phase12;

use App\Contracts\PayMongoClient;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Tests\Support\FakePayMongoClient;
use Tests\Support\PayMongoEventFactory;
use Tests\TestCase;

/**
 * /webhook is the path PayMongo's own form uses as its placeholder, and it is
 * the path the currently saved endpoint URL points at. The dashboard refuses to
 * accept an edited URL while deliveries are still queued, so the queued events
 * cannot be redirected by hand.
 *
 * The alias exists so those deliveries can land. It is temporary, and this test
 * names the condition that must hold before it is removed: once the saved URL
 * is the canonical path, the alias has served its purpose.
 */
class WebhookPlaceholderPathTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'test-signing-key-placeholder';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PayMongoClient::class, new FakePayMongoClient);

        config([
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_the_placeholder_path_is_a_post_route(): void
    {
        $this->assertNotNull(
            $this->routeFor('POST', 'webhook'),
            '/webhook must accept POSTs while the saved endpoint URL still points there.'
        );
    }

    public function test_the_placeholder_path_is_not_csrf_protected(): void
    {
        // The provider holds no session, so a CSRF check would reject every
        // delivery with a 419 before the signature is even looked at.
        $this->assertTrue(
            $this->pathIsCsrfExempt('webhook'),
            '/webhook must be CSRF exempt for the same reason /webhooks/paymongo is.'
        );
    }

    public function test_a_signed_paid_event_on_the_placeholder_path_settles_the_payment(): void
    {
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $payload = PayMongoEventFactory::checkoutSessionPaid($payment, 'evt_placeholder_1');

        $response = $this->call(
            'POST',
            '/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => PayMongoEventFactory::signatureHeadersForRawBody(
                    (string) json_encode($payload), $this->webhookSecret
                )['Paymongo-Signature'],
            ],
            (string) json_encode($payload)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('processed', $response->getData(true)['status']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_the_placeholder_path_still_verifies_the_signature(): void
    {
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $payload = PayMongoEventFactory::checkoutSessionPaid($payment, 'evt_placeholder_2');

        $this->postJson('/webhook', $payload)->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertFalse(PaymentEvent::query()->firstOrFail()->signature_verified);
    }

    public function test_both_paths_reach_the_same_handler(): void
    {
        $canonical = $this->routeFor('POST', 'webhooks/paymongo');
        $placeholder = $this->routeFor('POST', 'webhook');

        $this->assertNotNull($canonical);
        $this->assertNotNull($placeholder);
        $this->assertSame(
            $canonical->getActionName(),
            $placeholder->getActionName(),
            'The alias must not be a second implementation.'
        );
    }

    private function routeFor(string $method, string $uri): ?Route
    {
        $match = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === $uri
                && in_array($method, $route->methods(), true));

        return $match;
    }

    private function pathIsCsrfExempt(string $uri): bool
    {
        foreach (config('lms.csrf_exempt_paths', []) as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Payment}
     */
    private function pendingPayment(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);

        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount_minor' => 125000,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
            'provider_checkout_id' => 'cs_placeholder_1',
        ]);
        $payment->save();

        return [$student, $enrollment, $payment];
    }
}
