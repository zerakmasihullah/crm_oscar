<?php

namespace Tests\Unit;

use App\Services\Ai\AnalyticsIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsIntentResolverTest extends TestCase
{
    public function test_delete_request_is_refused(): void
    {
        $resolver = new AnalyticsIntentResolver;
        $result = $resolver->resolve('delete these users');

        $this->assertSame('refuse', $result['intent']);
        $this->assertStringContainsString('read-only', strtolower((string) $result['message']));
        $this->assertStringContainsString('can’t delete', strtolower((string) $result['message']));
    }

    public function test_create_and_update_requests_are_refused(): void
    {
        $resolver = new AnalyticsIntentResolver;

        $this->assertSame('refuse', $resolver->resolve('create a new user named Bob')['intent']);
        $this->assertSame('refuse', $resolver->resolve('update all inactive accounts')['intent']);
        $this->assertSame('refuse', $resolver->resolve('remove these field reports')['intent']);
    }

    public function test_chart_change_follow_up_is_analytics_not_refuse(): void
    {
        $resolver = new AnalyticsIntentResolver;
        $history = [
            ['role' => 'user', 'content' => 'users overview'],
            ['role' => 'assistant', 'content' => '[report] User overview. KPIs: Total=3'],
        ];

        $result = $resolver->resolve('change that to a pie chart', $history);

        $this->assertSame('analytics', $result['intent']);
    }

    public function test_help_lists_read_only_limits(): void
    {
        $resolver = new AnalyticsIntentResolver;
        $result = $resolver->resolve('what can you do');

        $this->assertSame('chat', $result['intent']);
        $this->assertStringContainsString('read-only', strtolower((string) $result['message']));
        $this->assertStringContainsString('cannot create', strtolower((string) $result['message']));
    }

    #[DataProvider('analyticsPrompts')]
    public function test_analytics_prompts(string $prompt): void
    {
        $resolver = new AnalyticsIntentResolver;
        $this->assertSame('analytics', $resolver->resolve($prompt)['intent']);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function analyticsPrompts(): array
    {
        return [
            ['Give me information about users'],
            ['login activity for users'],
            ['field reports by status'],
            ['how many users are active'],
        ];
    }
}
