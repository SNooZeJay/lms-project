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
use Tests\Support\FakePayMongoClient;
use Tests\TestCase;

/**
 * These payloads are copied verbatim from the Hosted Checkout documentation
 * page, not written by hand.
 *
 * The earlier suite built a different envelope that the documentation also
 * describes, for a different product. That is why those tests passed while
 * the real integration would have been rejected. This file pins the shape the
 * integration actually depends on, so a future edit to the documentation has
 * to be made deliberately.
 *
 * @see https://docs.paymongo.com/docs/payment-channels-hosted-checkout
 */
class HostedCheckoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_hosted_checkout';

    private FakePayMongoClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakePayMongoClient;
        $this->app->instance(PayMongoClient::class, $this->client);

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'placeholder-key',
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_the_documented_hosted_checkout_payload_activates_the_enrollment(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_1');

        $payload = $this->documentedPaidPayload($payment);

        $response = $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => hash_hmac('sha256', (string) json_encode($payload), $this->webhookSecret),
            ],
            (string) json_encode($payload)
        );

        $this->assertSame(200, $response->getStatusCode(), 'The documented payload must not be rejected.');

        $this->assertSame('processed', $response->getData(true)['status']);

        $payment->refresh();
        $enrollment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($enrollment->activated_at);
    }

    public function test_the_payment_id_comes_from_the_payments_array_not_the_session(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_2');

        $payload = $this->documentedPaidPayload($payment);

        $this->postDocumented($payload);

        // The Checkout Session id starts with cs_. Storing that as the payment
        // id would lose the link to the real pay_ resource.
        $this->assertSame('pay_DOCUMENTED_ABC', $payment->fresh()->provider_payment_id);
    }

    public function test_the_event_type_is_read_from_data_type(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_3');

        $this->postDocumented($this->documentedPaidPayload($payment));

        $this->assertSame(
            'checkout_session.payment.paid',
            PaymentEvent::query()->firstOrFail()->event_type
        );
    }

    public function test_the_reference_number_still_correlates_the_payment(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_4');

        $this->postDocumented($this->documentedPaidPayload($payment));

        $event = PaymentEvent::query()->firstOrFail();

        $this->assertSame($payment->id, $event->payment_id);
    }

    public function test_a_replayed_delivery_is_still_recorded_exactly_once(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_5');

        $payload = $this->documentedPaidPayload($payment);

        $this->postDocumented($payload);
        $first = $payment->fresh()->paid_at;

        $this->postDocumented($payload);

        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertEquals($first, $payment->fresh()->paid_at);
    }

    public function test_a_live_event_is_ignored_on_a_test_server(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_6');

        $payload = $this->documentedPaidPayload($payment);
        $payload['data']['livemode'] = true;

        $this->postDocumented($payload);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_a_tampered_documented_payload_changes_nothing(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_DOCUMENTED_7');

        $payload = $this->documentedPaidPayload($payment);
        $signature = hash_hmac('sha256', (string) json_encode($payload), $this->webhookSecret);

        $payload['data']['data']['attributes']['reference_number'] = 'enrollment-someone-else';

        $response = $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => $signature,
            ],
            (string) json_encode($payload)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ignored', $response->getData(true)['status']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postDocumented(array $payload): void
    {
        $raw = (string) json_encode($payload);

        $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => hash_hmac('sha256', $raw, $this->webhookSecret),
            ],
            $raw
        )->assertOk();
    }

    /**
     * The payload exactly as the Hosted Checkout documentation shows it.
     *
     * @return array<string, mixed>
     */
    private function documentedPaidPayload(Payment $payment): array
    {
        return [
            'event_type' => 'send.webhook',
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'resource' => 'checkout_session',
                'livemode' => false,
                'organization_id' => 'org_example',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
                'data' => [
                    'id' => $payment->provider_checkout_id,
                    'type' => 'checkout_session',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/test',
                        'reference_number' => $payment->idempotency_key,
                        'livemode' => false,
                        'payment_intent' => [
                            'id' => 'pi_DOCUMENTED_XYZ',
                        ],
                        'payments' => [
                            [
                                'id' => 'pay_DOCUMENTED_ABC',
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => $payment->amount_minor,
                                    'fee' => 1850,
                                    'net_amount' => $payment->amount_minor - 1850,
                                    'currency' => 'PHP',
                                    'status' => 'paid',
                                    'billing' => [
                                        'name' => 'Test Student',
                                        'email' => 'student@example.test',
                                    ],
                                    'source' => [
                                        'type' => 'qrph',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Course, 3: Payment}
     */
    private function pendingPayment(string $checkoutId): array
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
            'provider_checkout_id' => $checkoutId,
        ]);
        $payment->save();

        return [$student, $enrollment, $course, $payment];
    }
}
