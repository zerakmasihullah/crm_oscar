<?php

namespace Tests\Unit;

use App\Services\Ai\AiAnalyticsService;
use App\Services\Ai\SqlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnalyticsSchemaDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_bare_table_name_strips_current_database_prefix(): void
    {
        $svc = new AiAnalyticsService(app(SqlGuard::class));

        $this->assertSame('users', $svc->bareTableName('users', 'kit'));
        $this->assertSame('users', $svc->bareTableName('kit.users', 'kit'));
        $this->assertSame('users', $svc->bareTableName('`kit`.`users`', 'kit'));
        $this->assertNull($svc->bareTableName('afghan_fruits.users', 'kit'));
        $this->assertSame('users', $svc->bareTableName('main.users', ':memory:'));
    }

    public function test_schema_digest_includes_allowed_bare_tables(): void
    {
        $svc = app(AiAnalyticsService::class);
        $digest = $svc->schemaDigest(
            config('ai.analytics.deny_tables', []),
            ['users', 'field_reports'],
        );

        $this->assertStringNotContainsString('(no tables)', $digest);
        $this->assertStringContainsString('users(', $digest);
        $this->assertStringContainsString('field_reports(', $digest);
        $this->assertDoesNotMatchRegularExpression('/\b\w+\.users\(/', $digest);
    }
}
