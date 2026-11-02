<?php

use App\Http\Controllers\Api\OscarAuthController;
use App\Http\Controllers\ApiDataController;
use App\Http\Controllers\Auth\FcmTokenController;
use App\Http\Controllers\FcmTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->post('/auth/update_fcm_token', [FcmTokenController::class, 'update']);

if (app()->environment('local', 'staging') || config('app.enable_fcm_test_routes', false)) {
    Route::get('/check-fcm-config', [FcmTestController::class, 'checkConfig']);
    Route::post('/test-fcm', [FcmTestController::class, 'testNotification']);
    Route::post('/test-order-notification', [FcmTestController::class, 'testOrderNotification']);
    Route::get('/fcm-test-page', [FcmTestController::class, 'testPage']);
}

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/login', [OscarAuthController::class, 'login']);
    Route::post('/register', [OscarAuthController::class, 'register']);
    Route::post('/forgot-password', [OscarAuthController::class, 'forgotPassword']);
    // Legacy mobile path (Django-era)
    Route::post('/user-verification/email', [OscarAuthController::class, 'forgotPassword']);
    Route::post('/user-verification/email/', [OscarAuthController::class, 'forgotPassword']);
    Route::post('/auth/social/google', [OscarAuthController::class, 'google']);
    Route::post('/auth/social/google/', [OscarAuthController::class, 'google']);
    Route::post('/auth/social/apple', [OscarAuthController::class, 'apple']);
    Route::post('/auth/social/apple/', [OscarAuthController::class, 'apple']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [OscarAuthController::class, 'logout']);
    Route::get('/user-profile/{id}', [OscarAuthController::class, 'user_profile']);
    Route::post('/user-profile-edit/{id}', [OscarAuthController::class, 'edit_user']);
    Route::post('/delete-account', [ApiDataController::class, 'delete_account']);
});

Route::get('/menu-item/{id}', [ApiDataController::class, 'get_menu_item_by_id']);
Route::get('/food-category', [ApiDataController::class, 'get_all_menus']);
Route::get('/food-category/{id}', [ApiDataController::class, 'get_menu_items_by_category']);
Route::get('/food-menu', [ApiDataController::class, 'get_all_menu_items']);
Route::post('/rating', [ApiDataController::class, 'rate_menu_item']);
Route::get('/general-rating', [ApiDataController::class, 'get_general_rating']);
Route::get('/popular-menu', [ApiDataController::class, 'get_menu_items_by_rating']);
Route::get('/search={text}', [ApiDataController::class, 'search']);
Route::post('/place-order', [ApiDataController::class, 'place_order']);
Route::get('/order-notification', [ApiDataController::class, 'getNotifications']);
Route::post('/order-notification/read/{id}', [ApiDataController::class, 'markAsRead']);
Route::get('/all-staffs', [ApiDataController::class, 'getActiveStaff']);
Route::post('/chat/send/', [ApiDataController::class, 'send_message']);
Route::get('/chat/receive/{user1_id}/{user2_id}', [ApiDataController::class, 'receive_message']);
Route::get('/fees', [ApiDataController::class, 'all_fess']);
Route::get('/settings', [ApiDataController::class, 'get_settings']);
Route::get('/discounts', [ApiDataController::class, 'get_active_discounts']);

Route::post('/food-size', [ApiDataController::class, 'create_size']);
Route::post('/order', [ApiDataController::class, 'create_order']);
Route::post('/order-items', [ApiDataController::class, 'create_order_items']);

Route::get('/orders/{customer_id}', [ApiDataController::class, 'customer_orders']);
Route::get('/delivery-orders/{delivery_man_id}', [ApiDataController::class, 'delivery_orders']);
Route::post('/order-delivery-complete/{order_id}', [ApiDataController::class, 'orderDeliveryCompleted']);

Route::get('/drinks', [ApiDataController::class, 'get_drinks']);

Route::post('/create-checkout-session', [ApiDataController::class, 'create_checkout_session']);
Route::get('/order-by-session/{sessionId}', [ApiDataController::class, 'get_order_by_session']);
Route::get('/stripe-key', [ApiDataController::class, 'get_stripe_key']);
Route::post('/create-payment-intent', [ApiDataController::class, 'create_payment_intent']);

Route::middleware('auth:sanctum')->get('/billing-portal-url', function (Request $request) {
    $user = $request->user();

    if (! $user->stripe_id) {
        $user->createAsStripeCustomer();
    }

    $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
    $billingPortalUrl = $user->billingPortalUrl($frontendUrl);

    return response()->json([
        'success' => true,
        'url' => $billingPortalUrl,
    ], 200);
});
