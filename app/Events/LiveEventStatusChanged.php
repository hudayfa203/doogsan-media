<?php

namespace App\Events;

use App\Models\LiveEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LiveEventStatusChanged implements ShouldBroadcastNow
{
    public function __construct(public LiveEvent $event) {}

    public function broadcastOn(): array
    {
        return [new Channel('live-event.'.$this->event->id), new Channel('edition.'.$this->event->locale)];
    }

    public function broadcastAs(): string
    {
        return 'live.status';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->event->id, 'status' => $this->event->status, 'title' => $this->event->title, 'url' => $this->event->url()];
    }
}
