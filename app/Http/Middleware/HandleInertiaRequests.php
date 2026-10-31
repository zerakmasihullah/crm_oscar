<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesRequestLocale;
use App\Models\OrgSetting;
use App\Services\LocaleRegistry;
use App\Services\NotificationService;
use App\Support\AiSettings;
use App\Support\CalendarSystem;
use App\Support\OfflineSettings;
use App\Support\Seo;
use App\Support\SystemControl;
use App\Support\ThemeColors;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use ResolvesRequestLocale;

    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);
        $locales = app(LocaleRegistry::class);
        $localeConfig = $locales->get($locale) ?? [];

        $appName = OrgSetting::getValue('app_name', config('app.name'));

        return [
            ...parent::share($request),
            'name' => $appName,
            'logo' => OrgSetting::logoUrl(),
            'favicon' => OrgSetting::faviconUrl(),
            'themeColors' => (function (): array {
                $theme = ThemeColors::current();

                return [
                    ...Arr::except($theme, ['sidebar_active']),
                    'sidebarActive' => $theme['sidebar_active'],
                ];
            })(),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'roles' => $request->user()->getRoleNames()->values()->all(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $locale,
            'dir' => $localeConfig['dir'] ?? 'ltr',
            'calendarSystem' => CalendarSystem::current(),
            'locales' => collect($locales->all())
                ->map(fn (array $config, string $code): array => [
                    'code' => $code,
                    'name' => $config['name'],
                    'native' => $config['native'],
                    'dir' => $config['dir'],
                ])
                ->values()
                ->all(),
            'translations' => $this->translationsFor($locale),
            'storefrontGoogleAuth' => \App\Support\GoogleAuth::enabled(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'print_order_id' => fn () => $request->session()->get('print_order_id'),
            ],
            'notifications' => fn () => $request->user()
                ? ['unread_count' => app(NotificationService::class)->unreadCount($request->user())]
                : null,
            'chatUnreadCount' => fn () => $request->user()?->can('chats.view')
                ? (int) \App\Models\Chat::query()
                    ->where('receiver', $request->user()->id)
                    ->where('is_read', false)
                    ->count()
                : 0,
            'seo' => fn () => config('kit.features.seo') ? Seo::shared() : null,
            'documentSeo' => fn () => $request->routeIs('home') && config('kit.features.seo')
                ? Seo::document([
                    'title' => OrgSetting::getValue('app_name', config('app.name')),
                    'description' => config('kit.seo.default_description'),
                    'path' => '/',
                    'site_name_only' => true,
                ])
                : null,
            'offlineEnabled' => OfflineSettings::enabled(),
            'offlineSettings' => fn () => OfflineSettings::forClient(),
            'aiSettings' => fn () => $request->user()
                ? AiSettings::forClient()
                : null,
            'kitFeatures' => SystemControl::forClient(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function translationsFor(string $locale): array
    {
        $fallbackLocale = config('app.fallback_locale', 'en');
        $fallback = $this->loadJsonTranslations($fallbackLocale);
        $current = $this->loadJsonTranslations($locale);

        return array_merge($fallback, $current);
    }

    /**
     * @return array<string, string>
     */
    protected function loadJsonTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<string, string> $translations */
        $translations = File::json($path);

        return $translations;
    }
}
