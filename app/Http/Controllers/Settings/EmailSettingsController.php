<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\OrgSetting;
use App\Notifications\SystemTestEmailNotification;
use App\Services\EmailLogService;
use App\Support\EfficientQuery;
use App\Support\MailSettings;
use App\Support\MailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmailSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.manage');

        return Inertia::render('settings/Email/Index', [
            'tab' => 'configuration',
            'settings' => MailSettings::forAdmin(),
            'mailers' => MailSettings::MAILERS,
            'encryptions' => MailSettings::ENCRYPTIONS,
            'templates' => MailTemplates::all(),
            'selectedTemplate' => MailTemplates::currentId(),
            'canManage' => true,
            'canViewLogs' => $request->user()?->can('audit.view') ?? false,
        ]);
    }

    public function templates(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.manage');

        return Inertia::render('settings/Email/Index', [
            'tab' => 'templates',
            'settings' => MailSettings::forAdmin(),
            'mailers' => MailSettings::MAILERS,
            'encryptions' => MailSettings::ENCRYPTIONS,
            'templates' => MailTemplates::all(),
            'selectedTemplate' => MailTemplates::currentId(),
            'canManage' => true,
            'canViewLogs' => $request->user()?->can('audit.view') ?? false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'mailer' => ['required', 'string', Rule::in(MailSettings::MAILERS)],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', Rule::in(MailSettings::ENCRYPTIONS)],
            'mail_alerts' => ['sometimes', 'boolean'],
        ]);

        if (($validated['mailer'] ?? null) === 'smtp') {
            $request->validate([
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'min:1', 'max:65535'],
            ]);
        }

        MailSettings::save([
            ...$validated,
            'mail_alerts' => $request->boolean('mail_alerts'),
        ]);

        return back()->with('success', __('Email settings saved.'));
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(MailTemplates::ids())],
        ]);

        MailTemplates::set($validated['template']);

        return back()->with('success', __('Email template updated. It will be used for all system emails.'));
    }

    public function sendTest(Request $request, EmailLogService $emailLogService): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        MailSettings::applyToConfig();
        MailTemplates::applyToConfig();

        $appName = (string) OrgSetting::getValue('app_name', config('app.name'));

        try {
            Notification::route('mail', $validated['to'])
                ->notifyNow(new SystemTestEmailNotification($appName));

            return back()->with('success', __('Test email sent with your selected template.'));
        } catch (Throwable $exception) {
            $emailLogService->recordFailure(
                subject: 'Test email from '.$appName,
                to: [['address' => $validated['to'], 'name' => null]],
                error: $exception->getMessage(),
                relatedType: SystemTestEmailNotification::class,
                metadata: ['source' => 'settings.email.test'],
            );

            return back()->with('error', __('Test email failed: :error', ['error' => $exception->getMessage()]));
        }
    }

    public function logs(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('audit.view') || $request->user()?->can('settings.manage'),
            403,
        );

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $mailer = $request->string('mailer')->trim()->toString();
        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $baseQuery = EmailLog::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    EfficientQuery::applySearch(
                        $builder,
                        $search,
                        prefixColumns: ['subject', 'from_address', 'from_name', 'message_id', 'related_type'],
                        containsColumns: ['error'],
                        exactColumns: ['message_id', 'from_address'],
                    );
                    EfficientQuery::orWhereUserMatches($builder, $search);
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($mailer !== '', fn ($query) => $query->where('mailer', $mailer));

        $logs = (clone $baseQuery)
            ->with('user:id,name,email')
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage)
            ->withQueryString();

        $counts = EfficientQuery::statusCounts($baseQuery, 'status', [
            'sent' => EmailLog::STATUS_SENT,
            'failed' => EmailLog::STATUS_FAILED,
        ]);

        $summary = [
            'total' => $counts['total'],
            'sent' => $counts['sent'],
            'failed' => $counts['failed'],
        ];

        return Inertia::render('settings/Email/Index', [
            'tab' => 'logs',
            'logs' => $logs,
            'summary' => $summary,
            'statuses' => [
                EmailLog::STATUS_SENT,
                EmailLog::STATUS_FAILED,
            ],
            'mailers' => MailSettings::MAILERS,
            'filters' => [
                'search' => $search ?: null,
                'status' => $status ?: null,
                'mailer' => $mailer ?: null,
            ],
            'settings' => $request->user()?->can('settings.manage')
                ? MailSettings::forAdmin()
                : null,
            'encryptions' => MailSettings::ENCRYPTIONS,
            'templates' => MailTemplates::all(),
            'selectedTemplate' => MailTemplates::currentId(),
            'canManage' => $request->user()?->can('settings.manage') ?? false,
            'canViewLogs' => true,
        ]);
    }
}
