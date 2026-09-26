<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ProcessPayMongoEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider calls this endpoint. It is public by design, so the signature
 * is the only thing that makes a request trustworthy.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPayMongoEvent $processEvent): JsonResponse
    {
        $eventId = (string) $request->input('id', '');
        $eventType = (string) $request->input('type', '');

        if ($eventId === '' || $eventType === '') {
            return response()->json(['message' => 'Missing event id or type.'], 422);
        }

        $event = $processEvent->handle(
            $eventId,
            $eventType,
            (array) $request->all(),
            $request->headers->all(),
        );

        // Always acknowledge so the provider stops retrying, and report the
        // recorded state without leaking internals.
        return response()->json([
            'received' => true,
            'status' => $event->processing_status->value,
        ], 200);
    }
}
