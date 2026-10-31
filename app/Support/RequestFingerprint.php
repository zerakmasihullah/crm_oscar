<?php

namespace App\Support;

use Illuminate\Http\Request;

class RequestFingerprint
{
    /**
     * @return array{
     *     ip_address: ?string,
     *     ip_addresses: list<string>,
     *     user_agent: ?string,
     *     device_type: string,
     *     browser: ?string,
     *     platform: ?string,
     *     session_id: ?string,
     *     request_method: string,
     *     request_path: string,
     *     referer: ?string,
     *     accept_language: ?string,
     *     metadata: array<string, mixed>
     * }
     */
    public static function fromRequest(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';
        $parsed = self::parseUserAgent($userAgent);

        return [
            'ip_address' => $request->ip(),
            'ip_addresses' => self::collectIpAddresses($request),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'request_method' => $request->method(),
            'request_path' => '/'.$request->path(),
            'referer' => $request->headers->get('referer'),
            'accept_language' => $request->headers->get('accept-language'),
            'metadata' => [
                'headers' => self::collectSafeHeaders($request),
                'host' => $request->getHost(),
                'is_secure' => $request->isSecure(),
                'is_ajax' => $request->ajax(),
                'is_json' => $request->expectsJson(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function collectIpAddresses(Request $request): array
    {
        $ips = [];

        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
            $value = $request->server($header);

            if (! is_string($value) || $value === '') {
                continue;
            }

            foreach (explode(',', $value) as $ip) {
                $ip = trim($ip);

                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && ! in_array($ip, $ips, true)) {
                    $ips[] = $ip;
                }
            }
        }

        $requestIp = $request->ip();

        if (is_string($requestIp) && $requestIp !== '' && ! in_array($requestIp, $ips, true)) {
            array_unshift($ips, $requestIp);
        }

        return $ips;
    }

    /**
     * @return array{browser: ?string, platform: ?string, device_type: string}
     */
    public static function parseUserAgent(string $userAgent): array
    {
        $browser = null;
        $platform = null;
        $deviceType = 'desktop';

        if (preg_match('/Edg\/([\d.]+)/', $userAgent, $matches)) {
            $browser = 'Edge '.$matches[1];
        } elseif (preg_match('/Chrome\/([\d.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome '.$matches[1];
        } elseif (preg_match('/Firefox\/([\d.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox '.$matches[1];
        } elseif (preg_match('/Version\/([\d.]+).*Safari/', $userAgent, $matches)) {
            $browser = 'Safari '.$matches[1];
        } elseif (preg_match('/Safari\/([\d.]+)/', $userAgent, $matches)) {
            $browser = 'Safari '.$matches[1];
        }

        if (preg_match('/Windows NT ([\d.]+)/', $userAgent, $matches)) {
            $platform = 'Windows '.$matches[1];
        } elseif (preg_match('/Mac OS X ([\d_]+)/', $userAgent, $matches)) {
            $platform = 'macOS '.str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Android ([\d.]+)/', $userAgent, $matches)) {
            $platform = 'Android '.$matches[1];
            $deviceType = 'mobile';
        } elseif (preg_match('/iPhone OS ([\d_]+)/', $userAgent, $matches)) {
            $platform = 'iOS '.str_replace('_', '.', $matches[1]);
            $deviceType = 'mobile';
        } elseif (preg_match('/iPad; CPU OS ([\d_]+)/', $userAgent, $matches)) {
            $platform = 'iPadOS '.str_replace('_', '.', $matches[1]);
            $deviceType = 'tablet';
        } elseif (str_contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        }

        if (preg_match('/Mobile|Android/i', $userAgent) && $deviceType === 'desktop') {
            $deviceType = 'mobile';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function collectSafeHeaders(Request $request): array
    {
        $headers = [];

        foreach ([
            'x-forwarded-for',
            'x-real-ip',
            'cf-connecting-ip',
            'cf-ipcountry',
            'sec-ch-ua',
            'sec-ch-ua-mobile',
            'sec-ch-ua-platform',
            'origin',
            'accept-encoding',
        ] as $name) {
            $value = $request->headers->get($name);

            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
