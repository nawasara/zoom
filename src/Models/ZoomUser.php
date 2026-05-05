<?php

namespace Nawasara\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Sync\Concerns\HasSyncStatus;

/**
 * Snapshot of Zoom users.
 */
class ZoomUser extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_zoom_users';

    protected $fillable = [
        'user_id',
        'email',
        'first_name',
        'last_name',
        'user_type', // 1=Basic, 2=Licensed, 3=On-prem
        'license_type', // Pro, Business, etc
        'status', // active, pending, inactive
        'last_login_at',
        'total_meetings_30d',
        'total_minutes_30d',
        'dept_id', // registry foreign key
        'zoom_created_at',
        'sync_status',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'zoom_created_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'total_meetings_30d' => 'integer',
        'total_minutes_30d' => 'integer',
    ];

    public function meetings(): HasMany
    {
        return $this->hasMany(ZoomMeeting::class, 'host_id', 'user_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ZoomRecording::class, 'owner_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('email', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term);
        });
    }

    public function scopeLicenseType($query, ?string $licenseType)
    {
        return $licenseType ? $query->where('license_type', $licenseType) : $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
