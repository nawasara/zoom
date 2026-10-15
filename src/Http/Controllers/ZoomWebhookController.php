<?php

namespace Nawasara\Zoom\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class ZoomWebhookController extends Controller
{
    /**
     * Batas umur kiriman (detik). Kiriman yang timestamp-nya lebih tua atau
     * lebih jauh di masa depan dari ini ditolak sebagai replay.
     */
    protected const MAX_SKEW_SECONDS = 300;

    /**
     * Handle Zoom webhook events (P2)
     * Verify webhook signature and process events
     *
     * Audit keamanan 15 Oktober 2026 (R3):
     *  - secret kosong dulu memicu TypeError (500); kini dijawab 503 tanpa
     *    memproses apa pun, supaya endpoint tidak pernah "terbuka" karena
     *    salah konfigurasi.
     *  - timestamp diperiksa dalam jendela 5 menit (anti replay).
     *  - tanda tangan mengikuti format resmi Zoom
     *    `v0=` + hex(HMAC-SHA256(secret, "v0:{timestamp}:{body}")) dan
     *    dibandingkan dengan hash_equals.
     */
    public function handle(Request $request)
    {
        $secret = (string) config('nawasara-zoom.webhook_secret', '');

        if ($secret === '') {
            Log::warning('Zoom webhook ditolak: ZOOM_WEBHOOK_SECRET belum diisi.');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        if (! $this->verifyWebhookSignature($request, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json('payload') ?? [];
        $event = $request->json('event');

        // Validasi URL endpoint dari Zoom (saat endpoint didaftarkan dan
        // dicek ulang berkala): balas plainToken yang di-HMAC dengan secret.
        if ($event === 'endpoint.url_validation') {
            $plainToken = (string) ($payload['plainToken'] ?? '');

            return response()->json([
                'plainToken' => $plainToken,
                'encryptedToken' => hash_hmac('sha256', $plainToken, $secret),
            ]);
        }

        match ($event) {
            'meeting.started' => $this->handleMeetingStarted(is_array($payload) ? $payload : []),
            'meeting.ended' => $this->handleMeetingEnded(is_array($payload) ? $payload : []),
            'recording.completed' => $this->handleRecordingCompleted(is_array($payload) ? $payload : []),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    protected function verifyWebhookSignature(Request $request, string $secret): bool
    {
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');
        $signature = (string) $request->header('x-zm-signature', '');

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        // Zoom mengirim detik; toleransi bila suatu saat dikirim milidetik.
        $ts = (int) $timestamp;
        if ($ts > 100000000000) {
            $ts = intdiv($ts, 1000);
        }

        if (abs(time() - $ts) > self::MAX_SKEW_SECONDS) {
            return false;
        }

        $message = "v0:{$timestamp}:{$request->getContent()}";
        $expectedSignature = 'v0='.hash_hmac('sha256', $message, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    protected function handleMeetingStarted(array $payload): void
    {
        // Update meeting status to 'started'
        Log::info('Meeting started', $payload);
    }

    protected function handleMeetingEnded(array $payload): void
    {
        // Update meeting status to 'finished'
        Log::info('Meeting ended', $payload);
    }

    protected function handleRecordingCompleted(array $payload): void
    {
        // Trigger recording sync
        Log::info('Recording completed', $payload);
    }
}
