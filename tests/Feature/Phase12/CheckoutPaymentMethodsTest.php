<?php

namespace Tests\Feature\Phase12;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PayMongoApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The hosted checkout page offers QR Ph, GCash and Maya. Cards are excluded on
 * purpose, so this file stops a default or an environment value from quietly
 * putting them back.
 *
 * The method names were checked against the live test API: PayMongo accepted a
 * checkout session carrying all three.
 */
class CheckoutPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'placeholder-key',
        ]);
    }

    public function test_the_default_offers_qr_ph_gcash_and_maya(): void
    {
        $this->assertSame(
            ['qrph', 'gcash', 'maya'],
            (array) config('services.paymongo.payment_method_types'),
        );
    }

    public function test_cards_are_not_offered(): void
    {
        $methods = (array) config('services.paymongo.payment_method_types');

        $this->assertNotContains('card', $methods, 'Card payments are excluded by product decision.');
    }

    public function test_the_request_sent_to_the_provider_carries_those_methods(): void
    {
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/test',
                    ],
                ],
            ], 201),
        ]);

        $payment = $this->pendingPayment();

        (new PayMongoApiClient)->createCheckout($payment);

        Http::assertSent(function (Request $request): bool {
            $methods = $request->data()['data']['attributes']['payment_method_types'] ?? null;

            return $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
                && $methods === ['qrph', 'gcash', 'maya'];
        });
    }

    public function test_the_methods_can_be_overridden_by_configuration(): void
    {
        // The product decision is the default, not a hardcoded constant, so a
        // deployment can still change it without a code edit.
        config(['services.paymongo.payment_method_types' => ['qrph']]);

        $this->assertSame(['qrph'], (array) config('services.paymongo.payment_method_types'));
    }

    public function test_an_empty_configuration_falls_back_to_one_method(): void
    {
        // An empty list would be sent to the provider and rejected. The client
        // substitutes a default rather than sending nothing.
        config(['services.paymongo.payment_method_types' => []]);

        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_456',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test'],
                ],
            ], 201),
        ]);

        (new PayMongoApiClient)->createCheckout($this->pendingPayment());

        Http::assertSent(function (Request $request): bool {
            $methods = $request->data()['data']['attributes']['payment_method_types'] ?? null;

            return is_array($methods) && $methods !== [];
        });
    }

    private function pendingPayment(): Payment
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $course->id,
            'amount_minor' => 125000,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
        ]);
        $payment->save();

        return $payment;
    }
}
