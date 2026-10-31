<?php

namespace App\Http\Controllers;

use App\Helpers\FcmNotificationHelper;
use App\Services\FirebaseService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FcmTestController extends Controller
{
    /**
     * Test FCM notification for a user
     * 
     * POST /api/test-fcm
     * Body: { "user_id": 1, "title": "Test", "body": "Test message" }
     */
    public function testNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'fcm_token' => 'nullable|string',
            'title' => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        // Get FCM token
        $fcmToken = $request->fcm_token;
        $user = null;

        if ($request->user_id) {
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }
            
            if (empty($user->fcm_token) && empty($fcmToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have an FCM token. Please provide fcm_token in request or update user token first.',
                    'user_id' => $user->id,
                ], 400);
            }
            
            $fcmToken = $fcmToken ?? $user->fcm_token;
        }

        if (empty($fcmToken)) {
            return response()->json([
                'success' => false,
                'message' => 'FCM token is required. Provide either user_id (with user having token) or fcm_token directly.',
            ], 400);
        }

        $title = $request->title ?? 'Test Notification';
        $body = $request->body ?? 'This is a test notification from the server.';

        try {
            $firebaseService = app(FirebaseService::class);
            $result = $firebaseService->sendToDevice($fcmToken, $title, $body, [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Notification sent successfully' : 'Failed to send notification',
                'user_id' => $user?->id,
                'fcm_token_preview' => substr($fcmToken, 0, 20) . '...',
                'title' => $title,
                'body' => $body,
            ], $result ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('FCM test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test order notification
     * 
     * POST /api/test-order-notification
     * Body: { "user_id": 1, "order_id": 123, "status": "accepted" }
     */
    public function testOrderNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'required|integer',
            'status' => 'nullable|string|in:placed,accepted,cancelled,rejected,completed,delivered',
        ]);

        $user = User::find($request->user_id);
        
        if (empty($user->fcm_token)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an FCM token. Please update the token first.',
            ], 400);
        }

        $status = $request->status ?? 'placed';

        try {
            $result = FcmNotificationHelper::sendOrderNotification($user, $request->order_id, $status);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Order notification sent successfully' : 'Failed to send notification',
                'order_id' => $request->order_id,
                'status' => $status,
            ], $result ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('FCM order notification test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check Firebase configuration
     * 
     * GET /api/check-fcm-config
     */
    public function checkConfig()
    {
        $credentialsPath = config('services.firebase.credentials_path');
        $fileExists = file_exists($credentialsPath);
        $isReadable = $fileExists && is_readable($credentialsPath);

        $config = [
            'credentials_path' => $credentialsPath,
            'file_exists' => $fileExists,
            'is_readable' => $isReadable,
            'project_id' => config('services.firebase.project_id'),
        ];

        if ($fileExists && $isReadable) {
            try {
                $credentials = json_decode(file_get_contents($credentialsPath), true);
                $config['credentials_valid'] = !empty($credentials['project_id']);
                $config['project_id_from_file'] = $credentials['project_id'] ?? null;
                
                // Try to initialize Firebase service
                try {
                    $firebaseService = app(FirebaseService::class);
                    $config['firebase_service_initialized'] = true;
                } catch (\Exception $e) {
                    $config['firebase_service_initialized'] = false;
                    $config['firebase_service_error'] = $e->getMessage();
                }
            } catch (\Exception $e) {
                $config['credentials_valid'] = false;
                $config['error'] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => $fileExists && $isReadable && ($config['firebase_service_initialized'] ?? false),
            'config' => $config,
            'message' => ($config['firebase_service_initialized'] ?? false)
                ? 'Firebase configuration looks good! ✅' 
                : 'Firebase configuration has issues. Check details below.',
        ]);
    }

    /**
     * Simple web interface for testing FCM
     * 
     * GET /api/fcm-test-page
     */
    public function testPage()
    {
        $users = User::whereNotNull('fcm_token')->get(['id', 'name', 'email']);
        
        return response()->json(['message' => 'FCM test page disabled in CMS; use /api/test-fcm instead']);
    }
}
