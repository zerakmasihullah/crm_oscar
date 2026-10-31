<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use App\Events\OrderCreatedEvent;
use Illuminate\Support\Facades\Broadcast;

class BroadcastHelper
{
    /**
     * Broadcast order created event to trigger sound notification
     */
    public static function broadcastOrderCreated($orderId = null): void
    {
        try {
            // Use Laravel's event broadcasting system
            // This will use the configured broadcaster from config/broadcasting.php
            event(new OrderCreatedEvent($orderId));
            
            Log::info('Order event broadcasted successfully', ['order_id' => $orderId]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast order event: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e
            ]);
            
            // Try direct Pusher as fallback if Laravel broadcasting fails
            try {
                $pusher = new \Pusher\Pusher(
                    config('broadcasting.connections.pusher.key', env('PUSHER_APP_KEY')),
                    config('broadcasting.connections.pusher.secret', env('PUSHER_APP_SECRET')),
                    config('broadcasting.connections.pusher.app_id', env('PUSHER_APP_ID')),
                    [
                        'cluster' => config('broadcasting.connections.pusher.options.cluster', env('PUSHER_APP_CLUSTER', 'eu')),
                        'encrypted' => true,
                        'useTLS' => true,
                    ]
                );
                
                $pusher->trigger('my-channel', 'my-event', [
                    'message' => 'New order placed',
                    'order_id' => $orderId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                
                Log::info('Order event broadcasted via direct Pusher fallback', ['order_id' => $orderId]);
            } catch (\Exception $pusherError) {
                Log::error('Direct Pusher broadcast also failed: ' . $pusherError->getMessage(), [
                    'order_id' => $orderId,
                    'exception' => $pusherError
                ]);
            }
        }
    }
}

