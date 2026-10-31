<?php

namespace App\Providers;

use App\Ai\AgentToolRegistry;
use App\Ai\Tools\Catalog\CreateRecord;
use App\Ai\Tools\Catalog\DeleteRecord;
use App\Ai\Tools\Catalog\GetRecord;
use App\Ai\Tools\Catalog\ListPermissions;
use App\Ai\Tools\Catalog\ListRecords;
use App\Ai\Tools\Catalog\ListResources;
use App\Ai\Tools\Catalog\RunAnalyticsQuery;
use App\Ai\Tools\Catalog\UpdateRecord;
use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogOutboundMail;
use App\Support\AiSettings;
use App\Support\GoogleAuth;
use App\Support\OfflineSettings;
use App\Support\MailSettings;
use App\Support\MailTemplates;
use App\Support\SystemControl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Apply before Fortify boots so registration / reset routes respect toggles.
        SystemControl::applyToConfig();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        SystemControl::applyToConfig();
        OfflineSettings::applyToConfig();
        MailSettings::applyToConfig();
        MailTemplates::applyToConfig();
        AiSettings::applyToConfig();
        GoogleAuth::applyToConfig();
        $this->registerAgentTools();

        Event::subscribe(LogAuthenticationActivity::class);
        Event::subscribe(LogOutboundMail::class);
    }

    /**
     * Register allowlisted tools for the General Chat agent.
     * Domain sections are registered in config/ai_resources.php — use generic catalog tools.
     */
    protected function registerAgentTools(): void
    {
        AgentToolRegistry::register([
            ListResources::class => 'read',
            ListRecords::class => 'read',
            GetRecord::class => 'read',
            ListPermissions::class => 'read',
            CreateRecord::class => 'create',
            UpdateRecord::class => 'update',
            DeleteRecord::class => 'delete',
            RunAnalyticsQuery::class => 'analytics',
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureGates(): void
    {
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('Owner')
                ? true
                : null;
        });

        // opcodesio/log-viewer — Settings → System logs (requires audit.view)
        Gate::define('viewLogViewer', function ($user) {
            return method_exists($user, 'can') && $user->can('audit.view');
        });
    }
}
