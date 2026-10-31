<?php

namespace Tests\Feature\Auth;

use App\Models\AuthenticationLog;
use App\Models\User;
use App\Services\IpGeolocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_with_fingerprint_and_private_ip_location(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $log = AuthenticationLog::query()->where('event', AuthenticationLog::EVENT_LOGIN_SUCCESS)->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->success);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->ip_address);
        $this->assertTrue($log->is_private_ip);
        $this->assertSame('Local / private network', $log->location_label);
        $this->assertNotNull($log->request_method);
        $this->assertNotNull($log->request_path);
        $this->assertIsArray($log->ip_addresses);
    }

    public function test_failed_login_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('authentication_logs', [
            'email' => $user->email,
            'event' => AuthenticationLog::EVENT_LOGIN_FAILED,
            'success' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }

    public function test_public_ip_geolocation_is_stored_on_login(): void
    {
        Http::fake([
            'ipwho.is/*' => Http::response([
                'success' => true,
                'country' => 'Afghanistan',
                'country_code' => 'AF',
                'region' => 'Kabul',
                'city' => 'Kabul',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'timezone' => ['id' => 'Asia/Kabul'],
                'connection' => [
                    'isp' => 'Afghan Telecom',
                    'org' => 'Afghan Telecom',
                    'asn' => 'AS12345',
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $log = AuthenticationLog::query()->where('event', AuthenticationLog::EVENT_LOGIN_SUCCESS)->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->is_private_ip);
        $this->assertSame('Kabul', $log->city);
        $this->assertSame('Afghanistan', $log->country);
        $this->assertSame('AF', $log->country_code);
        $this->assertSame('Afghan Telecom', $log->isp);
        $this->assertSame('Kabul, Kabul, Afghanistan', $log->location_label);
        $this->assertEqualsWithDelta(34.5553, (float) $log->latitude, 0.0001);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'ipwho.is/203.0.113.10'));
    }

    public function test_ip_geolocation_service_marks_loopback_as_private(): void
    {
        $result = app(IpGeolocationService::class)->lookup('127.0.0.1');

        $this->assertTrue($result['is_private_ip']);
        $this->assertSame('Local / private network', $result['location_label']);
        $this->assertNull($result['country']);
    }
}
