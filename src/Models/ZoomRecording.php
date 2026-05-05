<?php

namespace Nawasara\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nawasara\Sync\Concerns\HasSyncStatus;

/**
 * Snapshot of Zoom recordings.
 */
class ZoomRecording extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_zoom_recordings';

    protected $fillable = [
        'recording_id',
        'meeting_id',
        'owner_id', // zoom user_id (FK to ZoomUser)
        'topic',
        'start_time',
        'duration',
        'file_type', // MP4, M4A, VTT
        'file_size',
        'file_url',
        'play_url',
        'download_url',
        'status', // processing, completed
        'recording_type', // shared_screen_with_speaker_video, etc
        'zoom_created_at',
        'sync_status',
        'sync_error',
        'last_synced_at',
        'retention_days', // auto-delete after X days
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'zoom_created_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'duration' => 'integer',
        'file_size' => 'integer',
        'retention_days' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ZoomMeeting::class, 'meeting_id', 'meeting_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(ZoomUser::class, 'owner_id', 'user_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('topic', 'like', $term);
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

    public function scopeExpiring($query, int $daysThreshold = 7)
    {
        return $query->where('retention_days', '>', 0)
            ->whereDate('zoom_created_at', '<=', now()->subDays($this->retention_days - $daysThreshold));
    }

    public function getFileSizeMbAttribute(): string
    {
        $mb = $this->file_size ? ($this->file_size / (1024 * 1024)) : 0;
        return number_format($mb, 2).' MB';
    }

    public function getDurationMinutesAttribute(): int
    {
        return intval($this->duration / 60);
    }
}
