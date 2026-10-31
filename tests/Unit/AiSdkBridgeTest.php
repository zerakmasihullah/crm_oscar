<?php

namespace Tests\Unit;

use App\Support\AiSdkBridge;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AiSdkBridgeTest extends TestCase
{
    #[Test]
    public function it_maps_google_to_native_gemini_for_agent_chat(): void
    {
        $this->assertSame('gemini', AiSdkBridge::sdkProviderId('google'));
        $this->assertSame('openai', AiSdkBridge::sdkProviderId('openai'));
        $this->assertSame('anthropic', AiSdkBridge::sdkProviderId('anthropic'));
        $this->assertSame('openai-compatible', AiSdkBridge::sdkProviderId('together'));
    }

    #[Test]
    public function it_uses_native_gemini_url_even_when_kit_stores_openai_compat_base(): void
    {
        $url = AiSdkBridge::baseUrlFor([
            'id' => 'x',
            'label' => 'google',
            'provider' => 'google',
            'model' => 'gemini-3.6-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'api_key_set' => true,
        ], 'gemini');

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/', $url);
    }

    #[Test]
    public function it_prefers_non_auth_failure_when_failover_ends_on_bad_key(): void
    {
        $message = AiSdkBridge::userMessageFromFailures([
            new RuntimeException('HTTP request returned status code 400: thought_signature missing'),
            new RuntimeException('HTTP request returned status code 401: Incorrect API key provided'),
        ]);

        $this->assertStringContainsString('thought signature', $message);
        $this->assertStringNotContainsString('rejected the API key', $message);
    }
}
