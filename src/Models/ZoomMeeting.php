<?php

namespace Nawasara\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Keycloak\Support\KeycloakProfile;
use Nawasara\Sync\Concerns\HasSyncStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Snapshot of Zoom meetings.
 */
class ZoomMeeting extends Model
{
    use HasSyncStatus;
    use LogsActivity;

    protected $table = 'nawasara_zoom_meetings';

    protected $fillable = [
        'meeting_id',
        'host_id', // zoom user_id (FK to ZoomUser)
        'pj_user_id', // optional penanggung jawab — FK to users
        'topic',
        'start_time',
        'duration',
        'timezone',
        'password',
        'agenda',
        'status', // not_started, started, finished
        'type', // 1=instant, 2=scheduled, 3=recurring, 8=pmi (personal)
        'join_url',
        'recording_consent',
        'auto_recording', // none, local, cloud, both
        'waiting_room',
        'recurrence_config', // JSON: {type: daily|weekly|monthly, repeat_interval}
        'settings', // JSON: mute on entry, etc
        'zoom_created_at',
        'zoom_updated_at',
        'sync_status',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'zoom_created_at' => 'datetime',
        'zoom_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'duration' => 'integer',
        'recurrence_config' => 'array',
        'settings' => 'array',
        'recording_consent' => 'boolean',
        'waiting_room' => 'boolean',
    ];

    /**
     * Audit trail — record meaningful field changes only.
     *
     * Deliberately EXCLUDED:
     *   - password        : secret, must never land in the activity log
     *   - sync_status / sync_error / last_synced_at : flip on every sync
     *     cycle, would flood the log with non-user-driven noise
     *   - join_url / meeting_id : assigned by Zoom, not user-edited
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'host_id',
                'pj_user_id',
                'topic',
                'start_time',
                'duration',
                'agenda',
                'status',
                'auto_recording',
                'waiting_room',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Zoom meeting {$eventName}");
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(ZoomUser::class, 'host_id', 'user_id');
    }

    /**
     * Optional penanggung jawab — a real Laravel User (sourced from Keycloak).
     * Replaces the old manual registry PIC reference.
     */
    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'pj_user_id');
    }

    /**
     * Contact profile (name / NIP / WhatsApp / email) for the penanggung jawab,
     * resolved from the Keycloak snapshot. Returns a "not found" profile when
     * no PJ is set.
     */
    public function pjProfile(): KeycloakProfile
    {
        return KeycloakProfile::for($this->penanggungJawab);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ZoomRecording::class, 'meeting_id', 'meeting_id');
    }

    /**
     * start_time as a Carbon in the meeting's timezone (default Asia/Jakarta).
     * The column stores an absolute UTC instant; this is what the UI should
     * display so a WIB-scheduled meeting shows the WIB wall-clock time.
     */
    public function getStartTimeLocalAttribute(): ?\Carbon\Carbon
    {
        if (! $this->start_time) {
            return null;
        }
        $tz = $this->timezone ?: config('nawasara-zoom.timezone', 'Asia/Jakarta');

        return $this->start_time->copy()->timezone($tz);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'not_started')
            ->where('start_time', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('status', 'finished')
            ->orWhere(function ($q) {
                $q->where('status', 'not_started')
                    ->where('start_time', '<=', now());
            });
    }

    public function scopeHost($query, ?string $hostId)
    {
        return $hostId ? $query->where('host_id', $hostId) : $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('topic', 'like', $term)
                ->orWhere('agenda', 'like', $term);
        });
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('start_time', '>=', $from);
        }
        if ($to) {
            $query->where('start_time', '<=', $to);
        }
        return $query;
    }

    public function getIsRecurringAttribute(): bool
    {
        return $this->type == 3;
    }

    public function getCanRecordAttribute(): bool
    {
        return $this->auto_recording !== 'none';
    }
}
