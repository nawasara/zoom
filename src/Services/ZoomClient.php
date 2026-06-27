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
     * GET an endpoint and follow Zoom's next_page_token pagination until
     * exhausted, merging the items found under $itemsKey across every page.
     * Zoom caps page_size at 300; without this loop accounts with more
     * users/meetings/recordings than one page were silently truncated.
     *
     * Returns the last page's JSON but with $itemsKey replaced by the merged
     * list (so existing callers reading $response[$itemsKey] get everything).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    protected function getAllPages(string $url, string $itemsKey, array $params = []): array
    {
        $params = array_merge(['page_size' => 300], $params);
        $items = [];
        $last = [];
        $token = null;
        $guard = 0;

        do {
            $query = $params;
            if ($token) {
                // next_page_token is mutually exclusive with page_number.
                unset($query['page_number']);
                $query['next_page_token'] = $token;
            }

            $response = $this->api()->get($url, $query);
            if (! $response->successful()) {
                throw new \Exception("Failed to fetch {$url}: ".$response->body());
            }

            $last = $response->json();
            foreach (($last[$itemsKey] ?? []) as $item) {
                $items[] = $item;
            }

            $token = $last['next_page_token'] ?? null;
        } while ($token && $guard++ < 100);

        $last[$itemsKey] = $items;

        return $last;
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
        // Follows next_page_token, so accounts with >300 users sync fully.
        return $this->getAllPages('/users', 'users', $params);
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
        return $this->getAllPages("/users/{$userId}/meetings", 'meetings', array_merge(['type' => $type], $params));
    }

    /**
     * Historical meetings from the Reports API. Unlike /users/{id}/meetings
     * (which only returns *scheduled* meetings, and only for a short window),
     * /report/users/{id}/meetings returns EVERY meeting the user hosted in the
     * date range — including instant meetings — which is what "riwayat yang
     * telah lalu" needs. Date range is capped to one month per Zoom's API, so
     * callers loop month-by-month. Requires the report:read:admin scope on the
     * Zoom app; without it Zoom returns 400/4xx and this throws (the caller
     * logs + continues).
     *
     * @return array<string,mixed>  has a 'meetings' key (merged across pages)
     */
    public function getUserMeetingsReport(string $userId, string $from, string $to, array $params = []): array
    {
        return $this->getAllPages(
            "/report/users/{$userId}/meetings",
            'meetings',
            array_merge(['from' => $from, 'to' => $to, 'type' => 'past'], $params),
        );
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
     * List a user's cloud recordings. Pass 'from'/'to' (Y-m-d) to pull a
     * historical window — the recordings endpoint also defaults to a short
     * recent window, so backfill loops month-by-month like the meetings
     * report. Follows next_page_token.
     */
    public function getRecordings(string $userId, array $params = []): array
    {
        return $this->getAllPages("/users/{$userId}/recordings", 'meetings', $params);
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
