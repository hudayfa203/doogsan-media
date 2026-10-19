<?php

use Illuminate\Support\Facades\Broadcast;

// Public channels (no auth): edition.{en|so}, live-event.{id}

Broadcast::channel('newsroom', function ($user) {
    return $user->is_active && $user->hasPermission('articles.view')
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});
