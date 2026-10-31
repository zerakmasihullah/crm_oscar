<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFcmCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test 
                            {--user-id= : User ID to test with}
                            {--token= : FCM token to test with (optional, will use user\'s token if user-id provided)}
                            {--title= : Notification title}
                            {--body= : Notification body}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Firebase Cloud Messaging (FCM) notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Firebase FCM Test Tool');
        $this->newLine();

        // Step 1: Check Firebase Configuration
        $this->info('Step 1: Checking Firebase Configuration...');
        $credentialsPath = config('services.firebase.credentials_path');
        
        if (!file_exists($credentialsPath)) {
            $this->error("❌ Firebase credentials file not found at: {$credentialsPath}");
            return Command::FAILURE;
        }
        
        if (!is_readable($credentialsPath)) {
            $this->error("❌ Firebase credentials file is not readable: {$credentialsPath}");
            return Command::FAILURE;
        }
        
        $this->info("✅ Credentials file found: {$credentialsPath}");
        
        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (empty($credentials['project_id'])) {
                $this->error("❌ Invalid credentials file: project_id not found");
                return Command::FAILURE;
            }
            $this->info("✅ Project ID: {$credentials['project_id']}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to parse credentials file: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->newLine();

        // Step 2: Initialize Firebase Service
        $this->info('Step 2: Initializing Firebase Service...');
        try {
            $firebaseService = app(FirebaseService::class);
            $this->info("✅ Firebase service initialized successfully");
        } catch (\Exception $e) {
            $this->error("❌ Failed to initialize Firebase service: " . $e->getMessage());
            $this->error("   Check your credentials file and Firebase project settings");
            return Command::FAILURE;
        }
        
        $this->newLine();

        // Step 3: Get FCM Token
        $this->info('Step 3: Getting FCM Token...');
        $fcmToken = $this->option('token');
        $userId = $this->option('user-id');
        
        if (!$fcmToken && $userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("❌ User with ID {$userId} not found");
                return Command::FAILURE;
            }
            
            if (empty($user->fcm_token)) {
                $this->warn("⚠️  User {$user->name} (ID: {$userId}) does not have an FCM token");
                $this->info("   To test, you can:");
                $this->info("   1. Use --token option to provide a test token");
                $this->info("   2. Or update the user's FCM token via API: POST /api/auth/update_fcm_token");
                $this->newLine();
                
                if (!$this->confirm('Do you want to continue with a test token? (You can use any valid FCM token)', false)) {
                    return Command::SUCCESS;
                }
                
                $fcmToken = $this->ask('Enter FCM token to test with');
                if (empty($fcmToken)) {
                    $this->error("❌ FCM token is required");
                    return Command::FAILURE;
                }
            } else {
                $fcmToken = $user->fcm_token;
                $this->info("✅ Found FCM token for user: {$user->name} (ID: {$userId})");
                $this->info("   Token preview: " . substr($fcmToken, 0, 30) . "...");
            }
        } elseif (!$fcmToken) {
            $this->warn("⚠️  No FCM token provided");
            $this->info("   Options:");
            $this->info("   1. Use --user-id to get token from user");
            $this->info("   2. Use --token to provide token directly");
            $this->newLine();
            
            $fcmToken = $this->ask('Enter FCM token to test with');
            if (empty($fcmToken)) {
                $this->error("❌ FCM token is required");
                return Command::FAILURE;
            }
        }
        
        $this->newLine();

        // Step 4: Send Test Notification
        $this->info('Step 4: Sending Test Notification...');
        $title = $this->option('title') ?? 'Test Notification';
        $body = $this->option('body') ?? 'This is a test notification from Laravel Firebase service.';
        
        $this->info("   Title: {$title}");
        $this->info("   Body: {$body}");
        $this->info("   Token: " . substr($fcmToken, 0, 30) . "...");
        $this->newLine();
        
        try {
            $result = $firebaseService->sendToDevice(
                $fcmToken,
                $title,
                $body,
                [
                    'type' => 'test',
                    'timestamp' => now()->toIso8601String(),
                    'test' => true,
                ]
            );
            
            if ($result) {
                $this->info("✅ Notification sent successfully!");
                $this->newLine();
                $this->info("📱 If the token is valid and the device is online, the notification should appear.");
                $this->info("   Check the device/emulator to see if the notification was received.");
                $this->newLine();
                $this->info("💡 Note: If you don't see the notification:");
                $this->info("   - Verify the FCM token is correct and active");
                $this->info("   - Check Firebase Console for delivery status");
                $this->info("   - Check Laravel logs: storage/logs/laravel.log");
                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to send notification");
                $this->info("   Check Laravel logs for details: storage/logs/laravel.log");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error sending notification: " . $e->getMessage());
            $this->info("   Full error details in logs: storage/logs/laravel.log");
            return Command::FAILURE;
        }
    }
}



