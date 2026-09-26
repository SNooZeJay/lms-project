<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ProcessPayMongoEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider calls this endpoint. It is public by design, so the signature
 * is the only thing that makes a request trustworthy.
 *
 * The raw request body is captured before anything else touches it. Parsing
 * and re-encoding the payload changes the bytes, which silently breaks every
 * signature check, so verification must run against the original body.
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

        // The event envelope nests the id and the type. They are not at the
        // top level, so reading them there would make every real event look
        // malformed.
        $eventId = (string) data_get($payload, 'data.id', '');
        $eventType = (string) data_get($payload, 'data.attributes.type', '');

        if ($eventId === '' || $eventType === '') {
            return response()->json([
                'message' => 'The payload must contain data.id and data.attributes.type.',
            ], 422);
        }

        $event = $processEvent->handle(
            $eventId,
            $eventType,
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
