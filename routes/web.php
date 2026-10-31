<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\FieldNoteController;
use App\Http\Controllers\Admin\MarketingAudienceController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OscarUsersController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinanceReportController;
use App\Http\Controllers\Admin\PlatformRevenueController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PromotionRecipientController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\AiAnalyticsController;
use App\Http\Controllers\AiAssistController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer storefront (public ordering site)
|--------------------------------------------------------------------------
*/
Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::redirect('/order', '/');
Route::redirect('/order-online', '/');
Route::redirect('/menu', '/');
Route::redirect('/takeaway', '/');
Route::redirect('/delivery', '/');
Route::redirect('/contact', '/support');
Route::redirect('/about', '/support');

Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
Route::get('/my-orders', [StorefrontController::class, 'myOrders'])->name('storefront.my-orders');
Route::get('/order-success', [StorefrontController::class, 'orderSuccess'])->name('storefront.order-success');
Route::get('/support', [StorefrontController::class, 'support'])->name('storefront.support');
Route::get('/terms-of-use', [StorefrontController::class, 'termsOfUse'])->name('storefront.terms');
Route::get('/privacy-statement', [StorefrontController::class, 'privacyStatement'])->name('storefront.privacy');

Route::get('/admin', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    if (! auth()->user()->canAccessAdminPanel()) {
        auth()->logout();

        return redirect()
            ->route('home')
            ->with('error', 'This account is for ordering on the website only. Admin access is for authorized staff.');
    }

    return redirect()->route('dashboard');
})->name('admin.home');

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/unsubscribe', [\App\Http\Controllers\MarketingUnsubscribeController::class, 'show'])
    ->name('marketing.unsubscribe');
Route::get('/email/o/{recipient}', [\App\Http\Controllers\MarketingTrackController::class, 'open'])
    ->name('marketing.track.open');
Route::get('/email/c/{recipient}', [\App\Http\Controllers\MarketingTrackController::class, 'click'])
    ->name('marketing.track.click');

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'verified', 'admin.access'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::inertia('dev/gallery', 'dev/ComponentGallery')
        ->middleware('role:Owner')
        ->name('dev.gallery');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::get('/search', GlobalSearchController::class)
        ->middleware('throttle:60,1')
        ->name('search');

    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::post('/orders/pricing-preview', [OrderController::class, 'pricingPreview'])->name('orders.pricing-preview');
    Route::post('/orders/{order}/accept', [OrderController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('/orders/{order}/print/{page?}', [OrderController::class, 'print'])->name('orders.print');
    Route::resource('orders', OrderController::class);

    Route::redirect('platforms/{any?}', '/settings/platforms')->where('any', '.*');

    Route::resource('platform-revenues', PlatformRevenueController::class)
        ->parameters(['platform-revenues' => 'platformRevenue'])
        ->except(['show']);

    Route::resource('expenses', ExpenseController::class)
        ->except(['show']);
    Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])
        ->name('expenses.categories.store');

    Route::get('/finance/reports', [FinanceReportController::class, 'index'])
        ->name('finance.reports');

    Route::resource('oscar-users', OscarUsersController::class)
        ->parameters(['oscar-users' => 'user']);
    Route::patch('/oscar-users/{user}/toggle-active', [OscarUsersController::class, 'toggleActive'])
        ->name('oscar-users.toggle-active');

    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('/chats/{user}/messages', [ChatController::class, 'send'])->name('chats.send');
    Route::post('/chats/{user}/read', [ChatController::class, 'markRead'])->name('chats.read');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    Route::resource('field-notes', FieldNoteController::class)
        ->parameters(['field-notes' => 'fieldNote'])
        ->except(['show']);

    Route::resource('menus', MenuController::class);

    Route::resource('menu-items', MenuItemController::class)
        ->parameters(['menu-items' => 'menuItem']);
    Route::patch('menu-items/{menuItem}/toggle-status', [MenuItemController::class, 'toggleStatus'])
        ->name('menu-items.toggle-status');

    Route::resource('discounts', DiscountController::class)
        ->parameters(['discounts' => 'discount']);

    Route::resource('deliveries', DeliveryController::class)
        ->parameters(['deliveries' => 'delivery'])
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('reservations', ReservationController::class)
        ->parameters(['reservations' => 'reservation']);
    Route::patch('reservations/{reservation}/accepted', [ReservationController::class, 'toggleAccepted'])
        ->name('reservations.accepted');

    Route::get('/marketing', [MarketingController::class, 'index'])
        ->name('marketing.index');
    Route::get('/marketing/audience', [MarketingAudienceController::class, 'index'])
        ->name('marketing.audience');
    Route::post('/marketing/audience', [MarketingAudienceController::class, 'store'])
        ->name('marketing.audience.store');
    Route::patch('/marketing/audience/{signup}/toggle', [MarketingAudienceController::class, 'toggle'])
        ->name('marketing.audience.toggle');
    Route::delete('/marketing/audience/{signup}', [MarketingAudienceController::class, 'destroy'])
        ->name('marketing.audience.destroy');

    Route::resource('promotions', PromotionController::class)
        ->parameters(['promotions' => 'promotion']);
    Route::post('promotions/{promotion}/send', [PromotionController::class, 'send'])
        ->name('promotions.send');
    Route::post('promotions/{promotion}/resend', [PromotionController::class, 'resend'])
        ->name('promotions.resend');
    Route::post('promotions/{promotion}/duplicate', [PromotionController::class, 'duplicate'])
        ->name('promotions.duplicate');
    Route::get('promotions/{promotion}/preview', [PromotionController::class, 'preview'])
        ->name('promotions.preview');
    Route::post('promotions/{promotion}/test-email', [PromotionController::class, 'testEmail'])
        ->name('promotions.test-email');
    Route::post('promotions/{promotion}/schedule', [PromotionController::class, 'schedule'])
        ->name('promotions.schedule');

    Route::resource('promotion-recipients', PromotionRecipientController::class)
        ->parameters(['promotion-recipients' => 'promotionRecipient'])
        ->only(['index', 'show', 'destroy']);

    Route::post('/ai/assist', AiAssistController::class)
        ->middleware(['throttle:20,1', 'can:ai.assist'])
        ->name('ai.assist');

    Route::get('/ai/analytics', [AiAnalyticsController::class, 'index'])
        ->name('ai.analytics');

    Route::post('/ai/analytics/generate', [AiAnalyticsController::class, 'generate'])
        ->middleware(['throttle:10,1', 'can:ai.analytics'])
        ->name('ai.analytics.generate');

    Route::post('/ai/chat', [AiChatController::class, 'chat'])
        ->middleware(['throttle:20,1', 'can:ai.chat'])
        ->name('ai.chat');

    Route::post('/ai/chat/approve', [AiChatController::class, 'approve'])
        ->middleware(['throttle:20,1', 'can:ai.chat'])
        ->name('ai.chat.approve');

    Route::get('/ai/chat/conversations', [AiChatController::class, 'conversations'])
        ->middleware(['throttle:30,1', 'can:ai.chat'])
        ->name('ai.chat.conversations');

    Route::get('/ai/chat/conversations/{conversation}', [AiChatController::class, 'show'])
        ->middleware(['throttle:30,1', 'can:ai.chat'])
        ->name('ai.chat.conversations.show');

    Route::delete('/ai/chat/conversations/{conversation}', [AiChatController::class, 'destroy'])
        ->middleware(['throttle:20,1', 'can:ai.chat'])
        ->name('ai.chat.conversations.destroy');
});

require __DIR__.'/settings.php';

Route::get('/offline', function () {
    return response()->view('offline');
})->name('offline');
