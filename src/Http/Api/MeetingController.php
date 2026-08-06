<?php

namespace Nawasara\Zoom\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Zoom\Http\Resources\MeetingResource;
use Nawasara\Zoom\Models\ZoomMeeting;

/**
 * Public API jadwal rapat Zoom, dibaca dari snapshot lokal yang disinkronkan
 * berkala — bukan dari Zoom API langsung, supaya kuota rate limit Zoom tidak
 * habis dipakai konsumen dan agar gangguan di sisi Zoom tidak merambat ke
 * aplikasi yang menampilkan agenda.
 *
 * Read-only. Membuat dan menghapus rapat tetap lewat UI, di mana aksinya
 * tercatat dan tergerbang permission.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:zoom.meeting.read  → jadwal
 *   - scope:zoom.meeting.join  → tambahan, membuka join_url + password
 */
class MeetingController extends Controller
{
    /**
     * GET /api/v1/zoom/meetings
     * Scope: zoom.meeting.read
     *
     * Query params:
     *   q        — cari di topik / agenda
     *   host_id  — saring per host Zoom
     *   window   — upcoming (default) | past | all
     *   from,to  — rentang tanggal (ISO8601), menimpa `window`
     *   per_page — 1..100, default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = ZoomMeeting::query()->with('host')->withCount('recordings');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->search($q);
        }

        if ($hostId = trim((string) $request->query('host_id', ''))) {
            $query->host($hostId);
        }

        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        if ($from !== '' || $to !== '') {
            $query->dateRange($from ?: null, $to ?: null);
        } else {
            // Default `upcoming`: konsumen yang menampilkan agenda hampir
            // selalu memaksudkan rapat yang belum lewat, dan menyertakan
            // seluruh riwayat diam-diam membuat halaman mereka berat tanpa
            // ada yang meminta.
            $window = (string) $request->query('window', 'upcoming');

            if ($window === 'upcoming') {
                $query->upcoming();
            } elseif ($window === 'past') {
                $query->past();
            }
            // window=all → tanpa filter waktu.
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $meetings = $query
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => MeetingResource::collection($meetings->items())->resolve($request),
            'meta' => [
                'total' => $meetings->total(),
                'per_page' => $meetings->perPage(),
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/zoom/meetings/{meetingId}
     * Scope: zoom.meeting.read
     */
    public function show(Request $request, string $meetingId): JsonResponse
    {
        $meeting = ZoomMeeting::with('host')
            ->withCount('recordings')
            ->where('meeting_id', $meetingId)
            ->first();

        if (! $meeting) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Meeting tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => (new MeetingResource($meeting))->resolve($request),
        ]);
    }
}
