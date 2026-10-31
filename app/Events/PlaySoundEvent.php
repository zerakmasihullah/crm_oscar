<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PlaySoundEvent implements ShouldBroadcast
{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct()
    {
        Log::info('PlaySoundEvent is being broadcasted');
    }

    // public function broadcastOn()
    // {
    //     return new Channel('orders');
    // }

    public function broadcastOn()
    {
        return new Channel('my-channel');
    }

    // public function broadcastAs()
    // {
    //     return 'new-order';
    // }
}
