<?php

namespace App\Helpers;

use App\Services\FirebaseService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FcmNotificationHelper
{
    /**
     * Send FCM notification to a user
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public static function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        try {
            $firebaseService = app(FirebaseService::class);
            return $firebaseService->sendToUser($user, $title, $body, $data);
        } catch (\Exception $e) {
            Log::error('FCM notification helper error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order notification to customer
     *
     * @param User $customer
     * @param int $orderId
     * @param string|int $status Status as string ('placed', 'accepted', etc.) or int (1=pending, 2=accepted, 3=rejected, 4=delivering, 5=completed)
     * @return bool
     */
    public static function sendOrderNotification(User $customer, int $orderId, $status = 'placed'): bool
    {
        // Convert integer status to string if needed
        if (is_int($status)) {
            $statusMap = [
                1 => 'placed',
                2 => 'accepted',
                3 => 'cancelled',
                4 => 'out_for_delivery',
                5 => 'delivered',
            ];
            $status = $statusMap[$status] ?? 'placed';
        }

        $messages = [
            'placed' => [
                'title' => 'Order Placed',
                'body' => 'Your order has been placed successfully! We will notify you when it\'s ready.',
            ],
            'accepted' => [
                'title' => 'Order Accepted',
                'body' => 'Your order has been accepted and is being prepared!',
            ],
            'confirmed' => [
                'title' => 'Order Confirmed',
                'body' => 'Your order has been confirmed and is being prepared.',
            ],
            'preparing' => [
                'title' => 'Order Preparing',
                'body' => 'Your order is being prepared.',
            ],
            'ready' => [
                'title' => 'Order Ready',
                'body' => 'Your order is ready for pickup/delivery!',
            ],
            'out_for_delivery' => [
                'title' => 'Out for Delivery',
                'body' => 'Your order is out for delivery!',
            ],
            'delivered' => [
                'title' => 'Order Delivered',
                'body' => 'Your order has been delivered. Enjoy your meal!',
            ],
            'completed' => [
                'title' => 'Order Completed',
                'body' => 'Your order has been completed. Thank you for your order!',
            ],
            'cancelled' => [
                'title' => 'Order Cancelled',
                'body' => 'Your order has been cancelled. If you have any questions, please contact us.',
            ],
            'rejected' => [
                'title' => 'Order Rejected',
                'body' => 'Your order has been rejected. If you have any questions, please contact us.',
            ],
        ];

        $message = $messages[$status] ?? $messages['placed'];

        $data = [
            'type' => 'order',
            'order_id' => (string) $orderId,
            'status' => $status,
        ];

        return self::sendToUser($customer, $message['title'], $message['body'], $data);
    }

    /**
     * Send notification to admins about new order
     *
     * @param int $orderId
     * @return array
     */
    public static function notifyAdminsAboutNewOrder(int $orderId): array
    {
        $admins = User::where('role', User::ROLE_ADMIN)->get();
        $results = [];

        foreach ($admins as $admin) {
            $results[$admin->id] = self::sendToUser(
                $admin,
                'New Order Placed',
                "A new order (#{$orderId}) has been placed. Please check the admin panel.",
                [
                    'type' => 'admin_order',
                    'order_id' => (string) $orderId,
                ]
            );
        }

        return $results;
    }

    /**
     * Send notification to delivery man about new order
     *
     * @param User $deliveryMan
     * @param int $orderId
     * @return bool
     */
    public static function notifyDeliveryManAboutOrder(User $deliveryMan, int $orderId): bool
    {
        return self::sendToUser(
            $deliveryMan,
            'New Delivery Order',
            "You have been assigned a new delivery order (#{$orderId}).",
            [
                'type' => 'delivery_order',
                'order_id' => (string) $orderId,
            ]
        );
    }

    /**
     * Send message notification to user
     *
     * @param User $receiver
     * @param User $sender
     * @param string $message
     * @return bool
     */
    public static function sendMessageNotification(User $receiver, User $sender, string $message): bool
    {
        $messagePreview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        
        return self::sendToUser(
            $receiver,
            'New Message from ' . $sender->name,
            $messagePreview,
            [
                'type' => 'message',
                'sender_id' => (string) $sender->id,
                'sender_name' => $sender->name,
            ]
        );
    }
}

