<?php

namespace App\Events;

use App\Models\BreakingAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Sends the complete current alert list for an edition, so clients simply replace theirs. */
class BreakingNewsChanged implements ShouldBroadcastNow
{
    public array $alerts;

    public function __construct(public string $locale)
    {
        $this->alerts = BreakingAlert::current($locale)->with('article.translations')->get()
            ->map->toPublicArray()->values()->all();
    }

    public function broadcastOn(): array
    {
        return [new Channel("edition.{$this->locale}")];
    }

    public function broadcastAs(): string
    {
        return 'breaking.changed';
    }

    public function broadcastWith(): array
    {
        return ['alerts' => $this->alerts];
    }
}
