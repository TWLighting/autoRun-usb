<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationEvent extends Event implements ShouldBroadcastNow
{
    public $message;
    public $account;

    public function __construct($message, $account)
    {
        $this->message = $message;
        $this->account = $account;
    }

    public function broadcastOn()
    {
        // return ['message'];
        return new Channel('notification-channel');
    }
}
