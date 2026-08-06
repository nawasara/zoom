<?php

namespace Nawasara\Zoom\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Zoom\Http\Resources\RecordingResource;
use Nawasara\Zoom\Models\ZoomRecording;

/**
 * Public API daftar rekaman rapat — metadata saja, tanpa tautan ke isinya.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:zoom.recording.read
 */
class RecordingController extends Controller
{
    /**
     * GET /api/v1/zoom/recordings
     * Scope: zoom.recording.read
     *
     * Query params:
     *   meeting_id — saring per rapat
     *   per_page   — 1..100, default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = ZoomRecording::query();

        if ($meetingId = trim((string) $request->query('meeting_id', ''))) {
            $query->where('meeting_id', $meetingId);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $recordings = $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => RecordingResource::collection($recordings->items())->resolve(),
            'meta' => [
                'total' => $recordings->total(),
                'per_page' => $recordings->perPage(),
                'current_page' => $recordings->currentPage(),
                'last_page' => $recordings->lastPage(),
            ],
        ]);
    }
}
