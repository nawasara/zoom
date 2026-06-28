<?php

namespace Nawasara\Zoom\Search;

use Nawasara\Search\Contracts\SearchProvider;
use Nawasara\Zoom\Models\ZoomMeeting;

class ZoomMeetingSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'zoom-meeting';
    }

    public function label(): string
    {
        return 'Zoom Meeting';
    }

    public function permission(): ?string
    {
        return 'zoom.meeting.view';
    }

    public function search(string $term, int $limit): array
    {
        return ZoomMeeting::query()
            ->search($term)
            ->orderByDesc('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (ZoomMeeting $m) => [
                'label' => $m->topic,
                'sublabel' => optional($m->start_time)->format('d M Y H:i') ?: 'Meeting',
                'url' => url('nawasara-zoom/meetings?search='.urlencode($term)),
            ])
            ->all();
    }
}
