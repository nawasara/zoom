<?php

namespace Nawasara\Zoom\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nawasara\Vault\Facades\Vault;

class ZoomClient
{
    protected ?string $clientId = null;
    protected ?string $clientSecret = null;
    protected ?string $accountId = null;

    protected function credentials(): array
    {
        return [
            'client_id' => $this->clientId ??= Vault::get('zoom', 'client_id'),
            'client_secret' => $this->clientSecret ??= Vault::get('zoom', 'client_secret'),
            'account_id' => $this->accountId ??= Vault::get('zoom', 'account_id'),
        ];
    }

    public function isConfigured(): bool
    {
        return Vault::has('zoom', 'client_id')
            && Vault::has('zoom', 'client_secret')
            && Vault::has('zoom', 'account_id');
    }

    /**
     * Get access token via Server-to-Server OAuth
     * Token cached for 55 minutes (expires in 1 hour)
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'nawasara_zoom_access_token';

        return Cache::remember($cacheKey, now()->addMinutes(55), function () {
            $creds = $this->credentials();

            $response = Http::baseUrl('https://zoom.us/oauth/token')
                ->asForm()
                ->withBasicAuth($creds['client_id'], $creds['client_secret'])
                ->post('/', [
                    'grant_type' => 'account_credentials',
                    'account_id' => $creds['account_id'],
                ])
                ->throw();

            return $response->json('access_token');
        });
    }

    protected function api(): PendingRequest
    {
        return Http::baseUrl('https://api.zoom.us/v2')
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Test connection to Zoom. Called from Vault UI.
     */
    public function testConnection(?string $instance = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Credential belum lengkap'];
        }

        try {
            $response = $this->api()->get('/users/me');

            if ($response->successful()) {
                $user = $response->json('first_name', 'User');
                return [
                    'success' => true,
                    'message' => "OAuth connection valid (user: {$user})",
                ];
            }

            $errors = $response->json('message', 'HTTP '.$response->status());
            return ['success' => false, 'message' => 'Gagal: '.$errors];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error: '.$e->getMessage()];
        }
    }

    // ─── Users ──────────────────────────────────────────

    /**
     * List all users in Zoom account
     */
    public function getUsers(array $params = []): array
    {
        $defaults = [
            'page_size' => 300,
            'page_number' => 1,
        ];

        $response = $this->api()->get('/users', array_merge($defaults, $params));

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch users: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get user detail by ID
     */
    public function getUser(string $userId): array
    {
        $response = $this->api()->get("/users/{$userId}");

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch user {$userId}: ".$response->body());
        }

        return $response->json();
    }

    // ─── Meetings ───────────────────────────────────────

    /**
     * List meetings for a user
     * $type: scheduled, live, upcoming, previous_meetings
     */
    public function getMeetings(string $userId, string $type = 'scheduled', array $params = []): array
    {
        $defaults = [
            'type' => $type,
            'page_size' => 300,
        ];

        $response = $this->api()->get("/users/{$userId}/meetings", array_merge($defaults, $params));

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch meetings for user {$userId}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Get meeting detail
     */
    public function getMeeting(string $meetingId): array
    {
        $response = $this->api()->get("/meetings/{$meetingId}");

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch meeting {$meetingId}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Create meeting for user
     */
    public function createMeeting(string $userId, array $data): array
    {
        $response = $this->api()->post("/users/{$userId}/meetings", $data);

        if (! $response->successful()) {
            throw new \Exception("Failed to create meeting: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Update meeting
     */
    public function updateMeeting(string $meetingId, array $data): void
    {
        $response = $this->api()->patch("/meetings/{$meetingId}", $data);

        if (! $response->successful()) {
            throw new \Exception("Failed to update meeting {$meetingId}: ".$response->body());
        }
    }

    /**
     * Delete meeting
     */
    public function deleteMeeting(string $meetingId, ?string $occurrenceId = null): void
    {
        $params = [];
        if ($occurrenceId) {
            $params['occurrence_id'] = $occurrenceId;
        }

        $response = $this->api()->delete("/meetings/{$meetingId}", $params);

        if (! $response->successful()) {
            throw new \Exception("Failed to delete meeting {$meetingId}: ".$response->body());
        }
    }

    // ─── Recordings ─────────────────────────────────────

    /**
     * List recordings for user
     */
    public function getRecordings(string $userId, array $params = []): array
    {
        $defaults = [
            'page_size' => 300,
            'page_number' => 1,
        ];

        $response = $this->api()->get("/users/{$userId}/recordings", array_merge($defaults, $params));

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch recordings for user {$userId}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * List recordings for meeting
     */
    public function getMeetingRecordings(string $meetingId): array
    {
        $response = $this->api()->get("/meetings/{$meetingId}/recordings");

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch recordings for meeting {$meetingId}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Delete recording
     */
    public function deleteRecording(string $meetingId, string $recordingId): void
    {
        $response = $this->api()->delete("/meetings/{$meetingId}/recordings/{$recordingId}");

        if (! $response->successful()) {
            throw new \Exception("Failed to delete recording {$recordingId}: ".$response->body());
        }
    }
}
