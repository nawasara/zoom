<?php

namespace Nawasara\Zoom\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ZoomWebhookController extends Controller
{
    /**
     * Handle Zoom webhook events (P2)
     * Verify webhook signature and process events
     */
    public function handle(Request $request)
    {
        // Verify Zoom webhook signature
        $token = config('nawasara-zoom.webhook_secret');

        if (! $this->verifyWebhookSignature($request, $token)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json('payload') ?? [];
        $event = $request->json('event');

        match ($event) {
            'meeting.started' => $this->handleMeetingStarted($payload),
            'meeting.ended' => $this->handleMeetingEnded($payload),
            'recording.completed' => $this->handleRecordingCompleted($payload),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    protected function verifyWebhookSignature(Request $request, string $token): bool
    {
        $timestamp = $request->header('x-zm-request-timestamp');
        $signature = $request->header('x-zm-signature');

        $message = "{$timestamp}:{$request->getContent()}";
        $hash = hash_hmac('sha256', $message, $token, true);
        $expectedSignature = 'v0='.base64_encode($hash);

        return hash_equals($expectedSignature, $signature);
    }

    protected function handleMeetingStarted(array $payload): void
    {
        // Update meeting status to 'started'
        \Log::info('Meeting started', $payload);
    }

    protected function handleMeetingEnded(array $payload): void
    {
        // Update meeting status to 'finished'
        \Log::info('Meeting ended', $payload);
    }

    protected function handleRecordingCompleted(array $payload): void
    {
        // Trigger recording sync
        \Log::info('Recording completed', $payload);
    }
}
