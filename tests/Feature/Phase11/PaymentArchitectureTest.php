<?php

namespace Tests\Feature\Phase11;

use App\Actions\Payments\CreatePayMongoCheckout;
use App\Actions\Payments\ProcessPayMongoEvent;
use App\Contracts\PayMongoClient;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Payments\PayMongoEventEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePayMongoClient;
use Tests\Support\PayMongoEventFactory;
use Tests\TestCase;

class PaymentArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private FakePayMongoClient $client;

    private string $webhookSecret = 'whsec_phase11';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakePayMongoClient;
        $this->app->instance(PayMongoClient::class, $this->client);

        config([
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_amount_comes_from_the_course_not_the_request(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);

        $this->actingAs($student)
            ->post($this->checkoutUrl($course), [
                'amount_minor' => 1,
                'currency' => 'USD',
                'status' => 'paid',
            ])
            ->assertRedirect();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(125000, $payment->amount_minor);
        $this->assertSame('PHP', $payment->currency);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_a_duplicate_idempotency_key_cannot_create_two_payments(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);

        $this->actingAs($student)->post($this->checkoutUrl($course));
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $this->actingAs($student)->post($this->checkoutUrl($course));

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_student_without_a_pending_enrollment_cannot_check_out(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled])->save();

        // The controller resolves the acting Student's own pending enrollment,
        // so a cancelled one is not even found.
        $this->actingAs($student)->post($this->checkoutUrl($course))->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_another_student_cannot_check_out_for_this_enrollment(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post($this->checkoutUrl($course))->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_instructor_cannot_check_out(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($instructor)->post($this->checkoutUrl($course))->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_guest_cannot_check_out(): void
    {
        [, , $course] = $this->pendingEnrollment(125000);

        $this->post($this->checkoutUrl($course))->assertRedirect(route('login'));
        $this->get($this->returnUrl($course))->assertRedirect(route('login'));
    }

    public function test_a_free_course_cannot_be_purchased(): void
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'currency' => 'PHP',
        ]);

        $student = User::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $this->actingAs($student)->post($this->checkoutUrl($course))->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_paid_event_activates_the_enrollment(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);

        $payment->refresh();
        $enrollment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($enrollment->activated_at);
    }

    public function test_a_repeated_event_changes_nothing(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);
        $paidAt = $payment->fresh()->paid_at;

        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);

        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertEquals($paidAt, $payment->fresh()->paid_at);
    }

    public function test_two_different_paid_events_still_activate_once(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);
        $this->processEvent('evt_paid_2', 'checkout_session.payment.paid', $payment);

        $this->assertSame(2, PaymentEvent::query()->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_an_invalid_signature_cannot_change_state(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->client->forceSignatureResult = false;

        $event = $this->processEvent('evt_bad_sig', 'checkout_session.payment.paid', $payment);

        $this->assertFalse($event->signature_verified);
        $this->assertSame(PaymentEventStatus::Ignored, $event->processing_status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_a_failed_event_keeps_the_enrollment_pending(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->processEvent('evt_failed_1', 'payment.failed', $payment);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('card_declined', $payment->failure_code);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_an_abandoned_checkout_never_settles(): void
    {
        // PayMongo has no "cancelled" payment event. A Student who closes the
        // checkout page simply produces no event, so the payment stays pending
        // and the enrollment stays pending_payment.
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_a_refund_removes_access(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);

        $this->processEvent('evt_refund_1', 'payment.refunded', $payment);

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->fresh()->status);
    }

    public function test_an_unknown_event_type_is_ignored(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $event = $this->processEvent('evt_other_1', 'payout.deposited', $payment);

        $this->assertSame(PaymentEventStatus::Ignored, $event->processing_status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_an_event_for_an_unknown_payment_is_ignored(): void
    {
        $event = $this->processEvent('evt_orphan_1', 'checkout_session.payment.paid', null);

        $this->assertSame(PaymentEventStatus::Ignored, $event->processing_status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_the_webhook_route_ignores_an_invalid_signature(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $this->client->forceSignatureResult = false;

        // The endpoint is public, so it acknowledges the delivery. What matters
        // is that no state changes.
        $payload = PayMongoEventFactory::checkoutSessionPaid($payment, 'evt_webhook_bad');

        $this->postJson($this->webhookUrl(), $payload, PayMongoEventFactory::signatureHeaders($payload, $this->webhookSecret))
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_the_webhook_is_public_and_processes_a_valid_event(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();

        $payload = PayMongoEventFactory::checkoutSessionPaid($payment, 'evt_webhook_ok');

        $this->postJson($this->webhookUrl(), $payload, PayMongoEventFactory::signatureHeaders($payload, $this->webhookSecret))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_the_return_page_shows_a_pending_state_before_payment(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));

        $this->actingAs($student)
            ->get($this->returnUrl($course))
            ->assertOk()
            ->assertSee('Waiting for payment confirmation')
            ->assertSee('This page does not confirm payment by itself.');
    }

    public function test_the_return_page_shows_paid_after_a_verified_event(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));
        $payment = Payment::query()->firstOrFail();
        $this->processEvent('evt_paid_1', 'checkout_session.payment.paid', $payment);

        $this->actingAs($student)
            ->get($this->returnUrl($course))
            ->assertOk()
            ->assertSee('Payment confirmed');
    }

    public function test_the_return_page_of_another_student_is_not_found(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get($this->returnUrl($course))->assertNotFound();
    }

    public function test_a_suspended_student_cannot_check_out(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $student->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();

        $this->actingAs($student->refresh())->post($this->checkoutUrl($course))->assertRedirect('/login');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_an_administrator_cannot_use_the_student_checkout(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)->post($this->checkoutUrl($course))->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_money_is_stored_as_integer_minor_units(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(9950);
        $this->actingAs($student)->post($this->checkoutUrl($course));

        $payment = Payment::query()->firstOrFail();

        $this->assertIsInt($payment->amount_minor);
        $this->assertSame(9950, $payment->amount_minor);
        $this->assertSame('PHP', $payment->currency);
    }

    public function test_no_secret_is_written_into_the_payment_record(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);
        $this->actingAs($student)->post($this->checkoutUrl($course));

        $payment = Payment::query()->firstOrFail();

        $this->assertArrayNotHasKey('secret_key', $payment->getAttributes());
        $this->assertArrayNotHasKey('api_key', $payment->getAttributes());
        $this->assertStringNotContainsString('sk_test', json_encode($payment->getAttributes()));
    }

    public function test_the_payment_action_is_used_through_the_container(): void
    {
        [$student, $enrollment, $course] = $this->pendingEnrollment(125000);

        $payment = app(CreatePayMongoCheckout::class)->handle($student, $enrollment);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->provider_checkout_id);
    }

    /**
     * Send a real event envelope for a Payment, signed with the test secret.
     */
    private function processEvent(string $id, string $type, ?Payment $payment): PaymentEvent
    {
        $payload = $payment === null
            ? PayMongoEventFactory::envelope($id, $type, new Payment, [
                'id' => 'cs_unknown',
                'type' => 'checkout_session',
                'attributes' => ['reference_number' => 'enrollment-does-not-exist'],
            ])
            : PayMongoEventFactory::envelope($id, $type, $payment, [
                'id' => $payment->provider_checkout_id ?: 'cs_test',
                'type' => 'checkout_session',
                'attributes' => [
                    'reference_number' => $payment->idempotency_key,
                    'status' => $type === 'payment.failed' ? 'failed' : 'paid',
                    'last_error' => ['code' => 'card_declined', 'message' => 'Declined'],
                ],
            ]);

        $raw = (string) json_encode($payload);

        return app(ProcessPayMongoEvent::class)->handle(
            PayMongoEventEnvelope::fromPayload($payload, $raw),
            $payload,
            PayMongoEventFactory::signatureHeaders($payload, $this->webhookSecret),
            $raw,
        );
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

    private function checkoutUrl(Course $course): string
    {
        return "/student/courses/{$course->id}/checkout";
    }

    private function returnUrl(Course $course): string
    {
        return "/student/courses/{$course->id}/checkout/return";
    }

    private function webhookUrl(): string
    {
        return '/webhooks/paymongo';
    }
}
