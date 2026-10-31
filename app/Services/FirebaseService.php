<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging = null;

    public function __construct()
    {
        try {
            $credentialsPath = config('services.firebase.credentials_path');
            
            if (!file_exists($credentialsPath)) {
                Log::warning("Firebase credentials file not found at: {$credentialsPath}");
                return;
            }

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath);

            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase initialization failed: ' . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Send a notification to a single device using FCM token
     *
     * @param string $token FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            if (empty($token)) {
                Log::warning('FCM token is empty, cannot send notification');
                return false;
            }

            // Expo Go / Expo push tokens (ExponentPushToken[...])
            if (str_starts_with($token, 'ExponentPushToken')) {
                return $this->sendViaExpoPush($token, $title, $body, $data);
            }

            if (!$this->messaging) {
                Log::warning('Firebase messaging not initialized; cannot send FCM');
                return false;
            }

            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification);

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            $this->messaging->send($message);
            
            Log::info('FCM notification sent successfully', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification: ' . $e->getMessage(), [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send via Expo Push API when the app stores an Expo push token.
     */
    protected function sendViaExpoPush(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $payload = [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => empty($data) ? new \stdClass() : $data,
                'priority' => 'high',
                'channelId' => 'oscars_high_importance',
            ];

            $ch = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Accept-Encoding: gzip, deflate',
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \RuntimeException($error);
            }

            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("Expo push HTTP {$status}: {$response}");
            }

            Log::info('Expo push notification sent', [
                'token' => substr($token, 0, 28) . '...',
                'title' => $title,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Expo push notification: ' . $e->getMessage(), [
                'token' => substr($token, 0, 28) . '...',
            ]);
            return false;
        }
    }

    /**
     * Send a notification to multiple devices
     *
     * @param array $tokens Array of FCM tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Results with success/failure for each token
     */
    public function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        
        foreach ($tokens as $token) {
            $results[$token] = $this->sendToDevice($token, $title, $body, $data);
        }

        return $results;
    }

    /**
     * Send a notification to a user
     *
     * @param \App\Models\User $user User model instance
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToUser($user, string $title, string $body, array $data = []): bool
    {
        if (!$user || empty($user->fcm_token)) {
            Log::warning('User does not have FCM token', [
                'user_id' => $user->id ?? null,
            ]);
            return false;
        }

        return $this->sendToDevice($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send a notification to multiple users
     *
     * @param \Illuminate\Database\Eloquent\Collection $users Collection of User models
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Results with user_id => success/failure
     */
    public function sendToUsers($users, string $title, string $body, array $data = []): array
    {
        $results = [];
        
        foreach ($users as $user) {
            if (!empty($user->fcm_token)) {
                $results[$user->id] = $this->sendToUser($user, $title, $body, $data);
            } else {
                $results[$user->id] = false;
            }
        }

        return $results;
    }

    /**
     * Send a notification with custom Android configuration
     *
     * @param string $token FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @param string|null $sound Sound file name
     * @param string|null $priority Priority level (normal or high)
     * @return bool
     */
    public function sendToDeviceWithAndroidConfig(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?string $sound = null,
        ?string $priority = 'high'
    ): bool {
        try {
            if (empty($token)) {
                Log::warning('FCM token is empty, cannot send notification');
                return false;
            }

            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification);

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            // Android specific configuration
            $androidConfig = AndroidConfig::fromArray([
                'priority' => $priority,
            ]);

            if ($sound) {
                $androidConfig = $androidConfig->withSound($sound);
            }

            $message = $message->withAndroidConfig($androidConfig);

            $this->messaging->send($message);
            
            Log::info('FCM notification sent with Android config', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'priority' => $priority,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification with Android config: ' . $e->getMessage(), [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}



