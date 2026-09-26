<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * The one boundary between this application and a payment provider.
 *
 * Phase 11 and Phase 12 tests bind a fake, so the payment state machine is
 * fully testable without live credentials.
 */
interface PayMongoClient
{
    /**
     * Ask the provider for a checkout session.
     *
     * @return array{checkout_id: string, redirect_url: string}
     */
    public function createCheckout(Payment $payment): array;

    /**
     * Verify a webhook signature against the raw request body.
     */
    public function verifySignature(string $rawBody, array $headers): bool;
}
