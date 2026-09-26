<?php

namespace Tests\Feature\Phase12;

use App\Actions\Payments\ProcessPayMongoEvent;
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
use App\Services\Payments\PayMongoApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PayMongoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'sk_test_placeholder',
            'services.paymongo.webhook_secret' => $this->webhookSecret,
        ]);

        $this->app->bind(PayMongoClient::class, PayMongoApiClient::class);
    }

    public function test_a_verified_signature_is_accepted(): void
    {
        $body = '{"id":"evt_1"}';
        $signature = hash_hmac('sha256', $body, $this->webhookSecret);

        $this->assertTrue((new PayMongoApiClient)->verifySignature($body, [
            'PayMongo-Signature' => $signature,
        ]));
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        $this->assertFalse((new PayMongoApiClient)->verifySignature('{"id":"evt_1"}', [
            'PayMongo-Signature' => hash_hmac('sha256', 'other body', $this->webhookSecret),
        ]));
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->assertFalse((new PayMongoApiClient)->verifySignature('{"id":"evt_1"}', []));
    }

    public function test_an_empty_signature_is_rejected(): void
    {
        $this->assertFalse((new PayMongoApiClient)->verifySignature('{"id":"evt_1"}', [
            'PayMongo-Signature' => '   ',
        ]));
    }

    public function test_a_missing_webhook_secret_never_verifies(): void
    {
        config(['services.paymongo.webhook_secret' => '']);

        $this->assertFalse((new PayMongoApiClient)->verifySignature('{"id":"evt_1"}', [
            'PayMongo-Signature' => hash_hmac('sha256', '{"id":"evt_1"}', ''),
        ]));
    }

    public function test_the_secret_never_appears_in_a_request_log_line(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'chk_test_1',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/abc'],
                ],
            ]),
        ]);

        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        (new PayMongoApiClient)->createCheckout($payment);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return ! str_contains($body, 'sk_test_placeholder')
                && ! str_contains($body, $this->webhookSecret);
        });
    }

    public function test_the_secret_key_is_sent_as_basic_auth_and_never_in_the_body(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'chk_test_1',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/abc'],
                ],
            ]),
        ]);

        [, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        (new PayMongoApiClient)->createCheckout($payment);

        Http::assertSent(function (Request $request): bool {
            return str_contains((string) $request->header('Authorization')[0], 'Basic')
                && ! str_contains($request->body(), 'sk_test');
        });
    }

    public function test_the_line_item_amount_comes_from_the_payment(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'chk_test_1',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/abc'],
                ],
            ]),
        ]);

        [, $enrollment, $course] = $this->pendingEnrollment(9950);
        $payment = $this->pendingPayment($enrollment, $course);

        (new PayMongoApiClient)->createCheckout($payment);

        Http::assertSent(function (Request $request): bool {
            $decoded = json_decode($request->body(), true);
            $line = $decoded['data']['attributes']['line_items'][0] ?? [];

            return ($line['amount'] ?? null) === 9950
                && ($line['currency'] ?? null) === 'PHP';
        });
    }

    public function test_payments_are_refused_when_the_provider_is_disabled(): void
    {
        config(['services.paymongo.enabled' => false]);

        [, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $this->expectException(RuntimeException::class);

        (new PayMongoApiClient)->createCheckout($payment);
    }

    public function test_a_provider_error_is_never_treated_as_a_checkout(): void
    {
        Http::fake(['*' => Http::response(['errors' => []], 422)]);

        [, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $this->expectException(RuntimeException::class);

        (new PayMongoApiClient)->createCheckout($payment);
    }

    public function test_an_incomplete_provider_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 'chk_only_id']], 200)]);

        [, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $this->expectException(RuntimeException::class);

        (new PayMongoApiClient)->createCheckout($payment);
    }

    public function test_a_signed_webhook_activates_one_paid_enrollment(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $payload = [
            'id' => 'evt_live_1',
            'type' => 'checkout.payment.paid',
            'data' => [
                'id' => 'pay_live_1',
                'attributes' => ['reference_number' => $payment->idempotency_key],
            ],
        ];

        $this->postJson($this->webhookUrl(), $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_replaying_a_signed_webhook_changes_nothing(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $payload = [
            'id' => 'evt_live_1',
            'type' => 'checkout.payment.paid',
            'data' => ['attributes' => ['reference_number' => $payment->idempotency_key]],
        ];

        $this->postJson($this->webhookUrl(), $payload, $this->signatureHeaders($payload))->assertOk();
        $this->postJson($this->webhookUrl(), $payload, $this->signatureHeaders($payload))->assertOk();

        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_a_tampered_webhook_body_is_rejected(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $payload = [
            'id' => 'evt_live_2',
            'type' => 'checkout.payment.paid',
            'data' => ['attributes' => ['reference_number' => $payment->idempotency_key]],
        ];

        $headers = $this->signatureHeaders($payload);

        // The attacker changes the body but keeps the original signature.
        $this->postJson($this->webhookUrl(), [
            'id' => 'evt_live_2',
            'type' => 'checkout.payment.paid',
            'data' => ['attributes' => ['reference_number' => 'enrollment-someone-else']],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_the_webhook_never_returns_a_stack_trace(): void
    {
        $this->withExceptionHandling();

        $response = $this->postJson($this->webhookUrl(), ['type' => 'checkout.payment.paid']);

        $response->assertStatus(422)->assertDontSee('vendor/laravel');
    }

    public function test_the_processor_is_idempotent_under_a_direct_call(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $payment = $this->pendingPayment($enrollment, $course);

        $headers = ['PayMongo-Signature' => hash_hmac('sha256', 'x', $this->webhookSecret)];

        $processor = app(ProcessPayMongoEvent::class);

        $processor->handle('evt_direct_1', 'checkout.payment.paid', [
            'data' => ['attributes' => ['reference_number' => $payment->idempotency_key]],
        ], $headers);

        $processor->handle('evt_direct_1', 'checkout.payment.paid', [
            'data' => ['attributes' => ['reference_number' => $payment->idempotency_key]],
        ], $headers);

        $this->assertSame(1, PaymentEvent::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(array $payload): array
    {
        $raw = (string) json_encode($payload);

        return ['PayMongo-Signature' => hash_hmac('sha256', $raw, $this->webhookSecret)];
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Course}
     */
    private function pendingEnrollment(int $priceMinor): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => $priceMinor,
            'currency' => 'PHP',
        ]);

        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        return [$student, $enrollment, $course];
    }

    private function pendingPayment(Enrollment $enrollment, Course $course): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $course->id,
            'amount_minor' => (int) $course->price_minor,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
        ]);
        $payment->save();

        return $payment;
    }

    private function webhookUrl(): string
    {
        return '/webhooks/paymongo';
    }
}
