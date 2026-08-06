<?php

namespace Nawasara\Zoom\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Zoom\Models\ZoomRecording;

/**
 * Transformer rekaman Zoom untuk public API — metadata saja.
 *
 * `download_url`, `play_url`, dan `file_url` TIDAK diekspos. Ketiganya adalah
 * tautan langsung ke isi rekaman rapat; sekali keluar lewat token, isi rapat
 * internal bisa diunduh siapa pun yang memegangnya, dan tidak ada cara
 * menariknya kembali.
 *
 * Konsumen yang perlu menonton rekaman diarahkan membukanya lewat Nawasara,
 * di mana aksesnya tercatat dan tergerbang permission.
 *
 * @mixin ZoomRecording
 */
class RecordingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recording_id' => (string) $this->recording_id,
            'meeting_id' => (string) $this->meeting_id,

            'topic' => $this->topic,
            'start_time' => $this->start_time?->toIso8601String(),
            'duration_minutes' => $this->duration !== null ? (int) $this->duration : null,

            'file_type' => $this->file_type,
            'recording_type' => $this->recording_type,
            'status' => $this->status,

            // Berapa lama rekaman disimpan sebelum dihapus Zoom. Berguna bagi
            // konsumen untuk tahu kapan tautannya akan berhenti bekerja.
            'retention_days' => $this->retention_days !== null ? (int) $this->retention_days : null,

            'created_at' => $this->zoom_created_at?->toIso8601String(),
        ];
    }
}
