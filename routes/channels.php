<?php

use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// The check-in kiosk of a club, which only its own staff may listen in on.
Broadcast::channel('clubs.{clubId}.check-ins', function (User $user, string $clubId): bool {
    $club = Club::query()->find($clubId);

    return $club !== null && $user->can('manage', $club);
});
