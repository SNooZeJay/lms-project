<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ProcessPayMongoEvent;
use App\Http\Controllers\Controller;
use App\Services\Payments\PayMongoEventEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider calls this endpoint. It is public by design, so the signature
 * is the only thing that makes a request trustworthy.
 *
 * The raw request body is captured before anything else touches it. Parsing
 * and re-encoding the payload changes the bytes, which silently breaks every
 * signature check, so verification must run against the original body.
 *
 * Reading the envelope is delegated so that both shapes PayMongo documents are
 * accepted. Reading a single shape here meant every event of the other product
 * came back as malformed.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPayMongoEvent $processEvent): JsonResponse
    {
        $rawBody = $request->getContent();

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'The request body must be a JSON object.'], 422);
        }

        $envelope = PayMongoEventEnvelope::fromPayload($payload, $rawBody);

        if ($envelope === null) {
            return response()->json([
                'message' => 'The payload does not look like a PayMongo event.',
            ], 422);
        }

        $event = $processEvent->handle(
            $envelope,
            $payload,
            $request->headers->all(),
            $rawBody,
        );

        // Always acknowledge so the provider stops retrying, and report the
        // recorded state without leaking internals.
        return response()->json([
            'received' => true,
            'status' => $event->processing_status->value,
        ], 200);
    }
}
