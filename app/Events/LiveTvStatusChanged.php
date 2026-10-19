<?php

namespace App\Events;

use App\Models\BroadcastChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LiveTvStatusChanged implements ShouldBroadcastNow
{
    public function __construct(public BroadcastChannel $channel) {}

    public function broadcastOn(): array
    {
        return [new Channel('edition.en'), new Channel('edition.so')];
    }

    public function broadcastAs(): string
    {
        return 'tv.status';
    }

    public function broadcastWith(): array
    {
        return ['channel' => $this->channel->slug, 'on_air' => $this->channel->isOnAir() && $this->channel->show_live_indicator];
    }
}
