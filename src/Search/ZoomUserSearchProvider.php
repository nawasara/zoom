<?php

namespace Nawasara\Zoom\Search;

use Nawasara\Search\Contracts\SearchProvider;
use Nawasara\Zoom\Models\ZoomUser;

class ZoomUserSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'zoom-user';
    }

    public function label(): string
    {
        return 'Zoom User';
    }

    public function permission(): ?string
    {
        return 'zoom.user.view';
    }

    public function search(string $term, int $limit): array
    {
        return ZoomUser::query()
            ->search($term)
            ->orderBy('first_name')
            ->limit($limit)
            ->get()
            ->map(fn (ZoomUser $u) => [
                'label' => trim($u->first_name.' '.$u->last_name) ?: $u->email,
                'sublabel' => $u->email,
                'url' => url('nawasara-zoom/users?search='.urlencode($term)),
            ])
            ->all();
    }
}
