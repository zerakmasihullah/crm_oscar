<?php

namespace Tests\Feature;

use App\Ai\Tools\Catalog\CreateRecord;
use App\Ai\Tools\Catalog\UpdateRecord;
use App\Models\AiConversation;
use App\Models\FieldReport;
use App\Models\User;
use App\Services\Ai\AiAnalyticsService;
use App\Services\Ai\AiChatService;
use App\Services\Ai\AiResourceCrudService;
use App\Services\Ai\AnalyticsAccess;
use App\Services\Ai\AnalyticsSafeFallback;
use App\Services\Ai\PrivilegeGrantGuard;
use App\Services\Ai\SqlGuard;
use App\Support\AiResourceCatalog;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class AiSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function enableAiWithKey(): void
    {
        config(['ai.conversations.generate_title' => false]);

        AiSettings::save([
            'enabled' => true,
            'chat_enabled' => true,
            'chat_allow_create' => true,
            'chat_allow_delete' => true,
            'chat_allow_analytics' => true,
            'langfuse_enabled' => false,
            'field_edit_enabled' => true,
            'default_model_id' => null,
            'models' => [
                [
                    'label' => 'Fast',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'base_url' => null,
                    'api_key' => 'sk-test-secret-key',
                ],
            ],
            'features' => [
                'grammar' => true,
                'improve' => true,
                'shorten' => true,
                'expand' => true,
                'describe' => true,
                'auto' => true,
                'custom' => true,
                'fill_form' => true,
            ],
            'failover_enabled' => false,
            'base_system_prompt' => '',
            'action_prompts' => [],
            'max_input_chars' => 1000,
            'max_output_tokens' => 200,
            'daily_token_budget' => 10_000,
            'panel_size' => 'comfortable',
        ]);
    }

    protected function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('SecTester-'.Str::random(6));

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);

        return $user;
    }

    public function test_catalog_helpers_are_config_driven(): void
    {
        $users = AiResourceCatalog::get('users');
        $this->assertTrue(AiResourceCatalog::requiresApproval($users, 'create'));
        $this->assertSame(['roles'], AiResourceCatalog::privilegeFields($users));

        $reports = AiResourceCatalog::get('field_reports');
        $this->assertFalse(AiResourceCatalog::requiresApproval($reports, 'create'));
        $this->assertTrue(AiResourceCatalog::requiresApproval($reports, 'delete'));
        $this->assertTrue(AiResourceCatalog::isUserScoped($reports));
    }

    public function test_conversation_continue_requires_ownership(): void
    {
        $this->enableAiWithKey();
        $owner = $this->userWithPermissions(['ai.chat']);
        $attacker = $this->userWithPermissions(['ai.chat']);

        $conversation = AiConversation::query()->create([
            'id' => (string) Str::uuid(),
            'participant_type' => $owner->getMorphClass(),
            'participant_id' => $owner->id,
            'title' => 'Secret',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conversation not found.');

        app(AiChatService::class)->chat($attacker, 'Summarize prior messages', (string) $conversation->id);
    }

    public function test_cannot_grant_permissions_actor_lacks(): void
    {
        $this->enableAiWithKey();
        Permission::findOrCreate('roles.create');
        Permission::findOrCreate('roles.view');
        Permission::findOrCreate('settings.manage');

        $actor = $this->userWithPermissions(['ai.chat', 'roles.create', 'roles.view']);

        $result = (string) (new CreateRecord($actor))->handle(new Request([
            'resource' => 'roles',
            'fields' => [
                'name' => 'Escalated',
                'permissions' => ['settings.manage'],
            ],
        ]));

        $this->assertStringContainsString('Error:', $result);
        $this->assertStringContainsString('settings.manage', $result);
        $this->assertFalse(Role::query()->where('name', 'Escalated')->exists());
    }

    public function test_cannot_assign_protected_owner_role(): void
    {
        $this->enableAiWithKey();
        Role::findOrCreate('Owner');
        $actor = $this->userWithPermissions(['ai.chat', 'users.create', 'users.view']);

        $result = (string) (new CreateRecord($actor))->handle(new Request([
            'resource' => 'users',
            'fields' => [
                'name' => 'Hacker',
                'email' => 'hacker@example.com',
                'password' => 'password123',
                'roles' => ['Owner'],
            ],
        ]));

        $this->assertStringContainsString('Error:', $result);
        $this->assertStringContainsString('protected role', strtolower($result));
    }

    public function test_users_create_requires_approval_via_catalog(): void
    {
        $users = AiResourceCatalog::get('users');
        $this->assertTrue(AiResourceCatalog::requiresApproval($users, 'create'));

        $tool = new CreateRecord;
        $approval = (new \ReflectionClass($tool))->getMethod('needsApproval');
        $approval->setAccessible(true);
        $result = $approval->invoke($tool, new Request(['resource' => 'users', 'fields' => []]));

        $this->assertNotFalse($result);
    }

    public function test_field_reports_create_does_not_require_approval(): void
    {
        $tool = new CreateRecord;
        $approval = (new \ReflectionClass($tool))->getMethod('needsApproval');
        $approval->setAccessible(true);
        $result = $approval->invoke($tool, new Request(['resource' => 'field_reports', 'fields' => []]));

        $this->assertFalse($result);
    }

    public function test_analytics_requires_ai_analytics_not_just_chat(): void
    {
        $this->enableAiWithKey();
        $user = $this->userWithPermissions(['ai.chat', 'field_reports.view']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You do not have permission to run analytics.');

        app(AiAnalyticsService::class)->run($user, 'count reports', 'SELECT 1 AS total');
    }

    public function test_analytics_scope_rejects_or_bypass(): void
    {
        $user = $this->userWithPermissions(['field_reports.view']);
        $access = new AnalyticsAccess;

        $this->expectException(InvalidArgumentException::class);

        $access->assertSqlRespectsScopes(
            'SELECT * FROM field_reports WHERE user_id = '.$user->id.' OR 1=1',
            $user,
        );
    }

    public function test_analytics_scope_rejects_string_literal_fake_scope(): void
    {
        $user = $this->userWithPermissions(['field_reports.view']);
        $access = new AnalyticsAccess;

        $this->expectException(InvalidArgumentException::class);

        $access->assertSqlRespectsScopes(
            "SELECT * FROM field_reports WHERE title = 'user_id = {$user->id}'",
            $user,
        );
    }

    public function test_sql_guard_rejects_aliased_deny_column(): void
    {
        $guard = new SqlGuard;
        $sql = $guard->sanitize('SELECT password AS secret FROM users LIMIT 10', 10);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertNoDeniedColumns($sql, ['password']);
    }

    public function test_safe_fallback_scopes_user_scoped_resources(): void
    {
        $owner = $this->userWithPermissions(['field_reports.view', 'ai.analytics']);
        $other = User::factory()->create(['is_active' => true]);

        FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'title' => 'Mine',
            'body' => null,
            'location' => null,
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);
        FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $other->id,
            'title' => 'Theirs',
            'body' => null,
            'location' => null,
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $sections = (new AnalyticsSafeFallback)->build(
            'field reports overview',
            ['field_reports'],
            $owner,
        );

        $this->assertNotEmpty($sections);
        foreach ($sections as $section) {
            $this->assertStringContainsString('user_id = '.(int) $owner->id, $section['sql']);
            if (($section['viz'] ?? '') === 'kpi' && str_contains(strtolower($section['title']), 'total')) {
                $this->assertSame(1, (int) ($section['rows'][0]['total'] ?? 0));
            }
        }
    }

    public function test_privilege_guard_allows_subset_of_actor_permissions(): void
    {
        Permission::findOrCreate('roles.view');
        Permission::findOrCreate('roles.create');
        $actor = $this->userWithPermissions(['roles.view', 'roles.create']);

        app(PrivilegeGrantGuard::class)->assertPermissionsAssignable($actor, ['roles.view']);

        $this->assertTrue(true);
    }

    public function test_generic_catalog_require_approval_flag(): void
    {
        config([
            'ai_resources._security_fixture' => [
                'label' => 'Security fixture',
                'model' => FieldReport::class,
                'permission' => 'field_reports',
                'require_approval' => ['create', 'update'],
                'privilege_fields' => [],
                'protected_names' => [],
                'user_scoped' => true,
                'sensitive' => [],
            ],
        ]);

        $resource = AiResourceCatalog::get('_security_fixture');
        $this->assertTrue(AiResourceCatalog::requiresApproval($resource, 'create'));
        $this->assertFalse(AiResourceCatalog::requiresApproval($resource, 'delete'));

        $tool = new UpdateRecord;
        $method = (new \ReflectionClass($tool))->getMethod('needsApproval');
        $method->setAccessible(true);
        $this->assertNotFalse($method->invoke($tool, new Request([
            'resource' => '_security_fixture',
            'id' => 1,
            'fields' => [],
        ])));
    }

    public function test_crud_service_blocks_privilege_escalation_on_update_role(): void
    {
        $this->enableAiWithKey();
        Permission::findOrCreate('roles.edit');
        Permission::findOrCreate('roles.view');
        Permission::findOrCreate('settings.manage');

        $actor = $this->userWithPermissions(['ai.chat', 'roles.edit', 'roles.view']);
        $role = Role::findOrCreate('LimitedClerk');
        $role->syncPermissions(['roles.view']);

        $result = app(AiResourceCrudService::class)->updateRecord($actor, 'roles', $role->id, [
            'permissions' => ['settings.manage'],
        ]);

        $this->assertStringContainsString('Error:', $result);
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('settings.manage'));
    }
}
