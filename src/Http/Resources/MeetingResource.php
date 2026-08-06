<?php

namespace Nawasara\Zoom\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Zoom\Models\ZoomMeeting;

/**
 * Transformer meeting Zoom untuk public API.
 *
 * Kredensial akses — `join_url`, `password`, `start_url` — TIDAK ikut secara
 * default. Ketiganya hanya muncul bila token membawa scope
 * `zoom.meeting.join`, yang sengaja dipisah dari `zoom.meeting.read`:
 * aplikasi yang menampilkan agenda rapat tidak perlu ikut memegang kunci
 * masuk ke dalamnya, dan memisahkannya berarti token bocor untuk satu
 * keperluan tidak otomatis membuka yang lain.
 *
 * `start_url` tidak pernah diekspos dalam keadaan apa pun: tautan itu
 * memulai rapat SEBAGAI host, jadi ia bukan sekadar akses masuk melainkan
 * kendali penuh atas rapat orang lain.
 *
 * @mixin ZoomMeeting
 */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'meeting_id' => (string) $this->meeting_id,
            'topic' => $this->topic,
            'agenda' => $this->agenda,

            'start_time' => $this->start_time?->toIso8601String(),
            'duration_minutes' => $this->duration !== null ? (int) $this->duration : null,
            'timezone' => $this->timezone,

            'status' => $this->status,
            'type' => $this->type,
            'is_recurring' => (bool) $this->is_recurring,

            // Host — nama dan email dinas, bukan identitas pribadi.
            'host' => $this->whenLoaded('host', fn () => [
                'user_id' => $this->host?->user_id,
                'email' => $this->host?->email,
                'name' => trim(($this->host?->first_name ?? '').' '.($this->host?->last_name ?? '')) ?: null,
            ]),

            // Penanggung jawab dari registry: nama diambil dari direktori
            // Keycloak supaya konsisten dengan seluruh aplikasi.
            'penanggung_jawab' => $this->when(
                $this->pj_user_id !== null,
                fn () => [
                    'name' => $this->pjProfile()->name,
                    'nip' => $this->pjProfile()->nip,
                ],
            ),

            'recordings_count' => $this->whenCounted('recordings'),

            'created_at' => $this->zoom_created_at?->toIso8601String(),
        ];

        // Kredensial masuk — hanya untuk token yang secara eksplisit diberi
        // scope join. Dicek dari token pada request, bukan dari konfigurasi,
        // supaya keputusannya per-token dan bukan per-deployment.
        if ($this->tokenCanJoin($request)) {
            $data['join_url'] = $this->join_url;
            $data['password'] = $this->password;
        }

        return $data;
    }

    /**
     * Apakah token pemanggil membawa scope `zoom.meeting.join`.
     *
     * Token disimpan di atribut request oleh middleware autentikasi API.
     * Ketiadaannya (mis. dipanggil dari konteks lain) diperlakukan sebagai
     * tidak boleh — gagal ke sisi tertutup.
     */
    protected function tokenCanJoin(Request $request): bool
    {
        $token = $request->attributes->get('api_token');

        if (! $token || ! method_exists($token, 'scopeNames')) {
            return false;
        }

        return in_array('zoom.meeting.join', $token->scopeNames(), true);
    }
}
