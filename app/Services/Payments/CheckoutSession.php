<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

/**
 * The result of asking the provider for a checkout.
 *
 * The provider returns two things that matter: an id to correlate the webhook
 * with, and the address the Student must actually visit to pay. The address is
 * never stored, because it is a single-use destination rather than a fact about
 * the payment, so it is returned here and used immediately.
 */
final class CheckoutSession
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $redirectUrl,
    ) {}

    /**
     * @throws RuntimeException when the provider hands back something that is
     *                          not an https address, because the Student would
     *                          be sent to a page that cannot be trusted.
     */
    public static function fromClientArray(Payment $payment, array $checkout): self
    {
        $url = (string) ($checkout['redirect_url'] ?? '');

        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('The payment provider did not return a usable checkout address.');
        }

        return new self($payment, $url);
    }
}
