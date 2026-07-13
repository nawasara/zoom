<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Nawasara\Notification\Facades\Notify;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Services\ZoomClient;

class CreateZoomMeetingJob extends AbstractZoomMeetingJob
{
    /**
     * @param  int  $localId  Primary key of the local placeholder ZoomMeeting
     *                        row. meeting_id (the Zoom ID) does not exist yet —
     *                        the Zoom API assigns it in handle().
     */
    public function __construct(
        protected int $localId,
        protected string $hostId,
        protected array $meetingData,
    ) {}

    public function handle(ZoomClient $client): void
    {
        $meeting = ZoomMeeting::findOrFail($this->localId);

        try {
            if (! $client->isConfigured()) {
                throw new \Exception('Zoom client not configured');
            }

            $meeting->update(['sync_status' => 'syncing']);

            $response = $client->createMeeting($this->hostId, $this->meetingData);

            // Zoom returns the meeting id as an integer; cast to string to
            // match the meeting_id column. We do NOT overwrite start_time from
            // the response — Zoom returns it in UTC (…Z), and we already stored
            // the correct absolute instant from the user's local pick. Trusting
            // the response here caused the timezone shift bug.
            $meeting->update([
                'meeting_id' => (string) $response['id'],
                'join_url' => $response['join_url'] ?? null,
                'password' => $response['password'] ?? $meeting->password,
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);

            $this->notifyPenanggungJawab($meeting->fresh());
        } catch (\Throwable $e) {
            $this->logError('Failed to create Zoom meeting', $e);

            $meeting->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Email the penanggung jawab the full meeting invite (topic, WIB date/time,
     * duration, Zoom join link) once the meeting is live on Zoom. No-op if there
     * is no PJ or the PJ has no email — never let a failed email fail the job.
     */
    protected function notifyPenanggungJawab(ZoomMeeting $meeting): void
    {
        try {
            $profile = $meeting->pjProfile();
            $email = $profile->email ?? null;

            if (! $email) {
                return;
            }

            $tz = $meeting->timezone ?: config('nawasara-zoom.timezone', 'Asia/Jakarta');
            $start = $meeting->start_time_local;
            \Carbon\Carbon::setLocale('id');
            $tanggal = $start ? $start->translatedFormat('l, d F Y').' pukul '.$start->format('H:i').' WIB' : '-';
            $join = $meeting->join_url;

            $body = view('nawasara-zoom::emails.meeting-invite', [
                'pjName' => $profile->name,
                'topic' => $meeting->topic,
                'tanggal' => $tanggal,
                'duration' => $meeting->duration,
                'joinUrl' => $join,
                'password' => $meeting->password,
                'agenda' => $meeting->agenda,
            ])->render();

            Notify::to($email)
                ->channel('email')
                ->subject('Undangan Zoom Meeting: '.$meeting->topic)
                ->body($body)
                ->send();
        } catch (\Throwable $e) {
            $this->logError('Zoom meeting created but PJ email failed', $e);
        }
    }
}
