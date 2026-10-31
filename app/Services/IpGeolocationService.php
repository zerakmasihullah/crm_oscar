<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IpGeolocationService
{
    /**
     * @return array{
     *     country: ?string,
     *     country_code: ?string,
     *     region: ?string,
     *     city: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     timezone: ?string,
     *     isp: ?string,
     *     org: ?string,
     *     asn: ?string,
     *     is_private_ip: bool,
     *     location_label: ?string
     * }
     */
    public function lookup(?string $ip, ?Request $request = null): array
    {
        $empty = $this->emptyResult(isPrivate: false);

        if ($ip === null || $ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $empty;
        }

        if ($this->isPrivateIp($ip)) {
            return $this->emptyResult(isPrivate: true, locationLabel: 'Local / private network');
        }

        $cached = Cache::remember(
            'ip_geo:'.md5($ip),
            now()->addDay(),
            fn () => $this->fetchFromProvider($ip),
        );

        if ($cached === null) {
            $countryCode = $request?->headers->get('cf-ipcountry');

            if (is_string($countryCode) && $countryCode !== '' && strtoupper($countryCode) !== 'XX') {
                return [
                    ...$this->emptyResult(isPrivate: false),
                    'country_code' => strtoupper($countryCode),
                    'location_label' => strtoupper($countryCode),
                ];
            }

            return $empty;
        }

        return $cached;
    }

    /**
     * @return array{
     *     country: ?string,
     *     country_code: ?string,
     *     region: ?string,
     *     city: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     timezone: ?string,
     *     isp: ?string,
     *     org: ?string,
     *     asn: ?string,
     *     is_private_ip: bool,
     *     location_label: ?string
     * }|null
     */
    protected function fetchFromProvider(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->get("https://ipwho.is/{$ip}");

            if (! $response->successful()) {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = $response->json();

            if (($data['success'] ?? false) !== true) {
                return null;
            }

            $country = is_string($data['country'] ?? null) ? $data['country'] : null;
            $countryCode = is_string($data['country_code'] ?? null) ? strtoupper($data['country_code']) : null;
            $region = is_string($data['region'] ?? null) ? $data['region'] : null;
            $city = is_string($data['city'] ?? null) ? $data['city'] : null;
            $latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
            $longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;

            $timezone = null;
            if (isset($data['timezone']) && is_array($data['timezone'])) {
                $timezone = is_string($data['timezone']['id'] ?? null) ? $data['timezone']['id'] : null;
            } elseif (is_string($data['timezone'] ?? null)) {
                $timezone = $data['timezone'];
            }

            $isp = null;
            $org = null;
            $asn = null;
            if (isset($data['connection']) && is_array($data['connection'])) {
                $isp = is_string($data['connection']['isp'] ?? null) ? $data['connection']['isp'] : null;
                $org = is_string($data['connection']['org'] ?? null) ? $data['connection']['org'] : null;
                $asn = is_string($data['connection']['asn'] ?? null) ? (string) $data['connection']['asn'] : null;
            }

            return [
                'country' => $country,
                'country_code' => $countryCode,
                'region' => $region,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $timezone,
                'isp' => $isp,
                'org' => $org,
                'asn' => $asn,
                'is_private_ip' => false,
                'location_label' => $this->buildLocationLabel($city, $region, $country),
            ];
        } catch (Throwable $exception) {
            Log::debug('IP geolocation lookup failed.', [
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * @return array{
     *     country: ?string,
     *     country_code: ?string,
     *     region: ?string,
     *     city: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     timezone: ?string,
     *     isp: ?string,
     *     org: ?string,
     *     asn: ?string,
     *     is_private_ip: bool,
     *     location_label: ?string
     * }
     */
    protected function emptyResult(bool $isPrivate, ?string $locationLabel = null): array
    {
        return [
            'country' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
            'isp' => null,
            'org' => null,
            'asn' => null,
            'is_private_ip' => $isPrivate,
            'location_label' => $locationLabel,
        ];
    }

    protected function buildLocationLabel(?string $city, ?string $region, ?string $country): ?string
    {
        $parts = array_values(array_filter([$city, $region, $country], fn ($part) => is_string($part) && $part !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
