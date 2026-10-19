<?php

namespace App\Events;

use App\Models\LiveEventUpdate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LiveUpdatePosted implements ShouldBroadcastNow
{
    public function __construct(public LiveEventUpdate $update, public string $action = 'posted') {}

    public function broadcastOn(): array
    {
        return [new Channel('live-event.'.$this->update->live_event_id)];
    }

    public function broadcastAs(): string
    {
        return 'update.'.$this->action; // update.posted | update.edited | update.deleted
    }

    public function broadcastWith(): array
    {
        return $this->action === 'deleted'
            ? ['id' => $this->update->id]
            : $this->update->loadMissing('author', 'media')->toBroadcastArray();
    }
}
