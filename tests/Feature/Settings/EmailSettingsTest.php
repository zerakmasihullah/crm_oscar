<?php

namespace Tests\Feature\Settings;

use App\Models\EmailLog;
use App\Models\User;
use App\Support\MailSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_view_email_configuration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.email.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Email/Index')
                ->where('tab', 'configuration')
                ->has('settings')
                ->where('canManage', true)
            );
    }

    public function test_owner_can_update_email_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->put(route('settings.email.update'), [
                'mailer' => 'log',
                'from_address' => 'noreply@example.com',
                'from_name' => 'Yaqeen Kit',
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'mailer',
                'password' => 'secret-pass',
                'encryption' => 'tls',
                'mail_alerts' => true,
            ])
            ->assertRedirect();

        $settings = MailSettings::forAdmin();

        $this->assertSame('log', $settings['mailer']);
        $this->assertSame('noreply@example.com', $settings['from_address']);
        $this->assertSame('Yaqeen Kit', $settings['from_name']);
        $this->assertTrue($settings['password_set']);
        $this->assertTrue($settings['mail_alerts']);
        $this->assertSame('tls', $settings['encryption']);
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_ssl_encryption_maps_to_smtps_scheme(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->put(route('settings.email.update'), [
                'mailer' => 'smtp',
                'from_address' => 'noreply@example.com',
                'from_name' => 'Yaqeen Kit',
                'host' => 'smtp.example.com',
                'port' => 465,
                'username' => 'mailer',
                'password' => 'secret-pass',
                'encryption' => 'ssl',
                'mail_alerts' => true,
            ])
            ->assertRedirect();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('ssl', MailSettings::forAdmin()['encryption']);
    }

    public function test_owner_can_view_and_select_email_template(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.email.templates'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Email/Index')
                ->where('tab', 'templates')
                ->has('templates', 4)
                ->where('selectedTemplate', 'classic')
            );

        $this->actingAs($user)
            ->put(route('settings.email.templates.update'), [
                'template' => 'modern',
            ])
            ->assertRedirect();

        $this->assertSame('modern', \App\Support\MailTemplates::currentId());
        $this->assertSame('modern', config('mail.markdown.theme'));
    }

    public function test_outbound_mail_is_recorded_in_email_log(): void
    {
        config(['mail.default' => 'array']);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->post(route('settings.email.test'), [
                'to' => $user->email,
            ])
            ->assertRedirect();

        $log = EmailLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        $this->assertSame($user->email, $log->to[0]['address'] ?? null);
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString('Test email', (string) $log->subject);
    }

    public function test_owner_can_view_email_logs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        EmailLog::query()->create([
            'user_id' => $user->id,
            'mailer' => 'log',
            'status' => EmailLog::STATUS_SENT,
            'subject' => 'Backup failed',
            'from_address' => 'alerts@example.com',
            'from_name' => 'Kit',
            'to' => [['address' => $user->email, 'name' => $user->name]],
            'related_type' => 'App\\Notifications\\CriticalAlertNotification',
            'body_preview' => 'A backup job failed.',
            'logged_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('settings.email.logs', ['search' => 'Backup']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Email/Index')
                ->where('tab', 'logs')
                ->has('logs.data', 1)
                ->has('summary')
                ->where('logs.data.0.subject', 'Backup failed')
            );
    }

    public function test_user_role_cannot_manage_email_settings_or_view_logs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('settings.email.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.email.logs'))
            ->assertForbidden();
    }
}
