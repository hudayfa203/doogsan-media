<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NewsroomActivity implements ShouldBroadcastNow
{
    public string $at;

    public function __construct(public string $user, public string $message, public ?int $articleId = null)
    {
        $this->at = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('newsroom')];
    }

    public function broadcastAs(): string
    {
        return 'activity';
    }
}
