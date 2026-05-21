<?php

namespace Nawasara\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Registry\Models\Pic;
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
        'pic_id', // optional FK to nawasara_registry_pic
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
                'pic_id',
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
     * Optional person in charge, from the registry package.
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class, 'pic_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ZoomRecording::class, 'meeting_id', 'meeting_id');
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
