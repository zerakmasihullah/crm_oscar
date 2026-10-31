<?php

namespace App\Support;

class AiPricing
{
    /**
     * Estimate USD cost from token counts.
     * Rates are USD per 1M tokens from config/ai.php pricing.
     */
    public static function estimateUsd(
        string $provider,
        string $modelName,
        int $promptTokens,
        int $completionTokens,
    ): float {
        [$inputPerMillion, $outputPerMillion] = self::rates($provider, $modelName);

        $cost = ($promptTokens / 1_000_000) * $inputPerMillion
            + ($completionTokens / 1_000_000) * $outputPerMillion;

        return round(max(0, $cost), 6);
    }

    /**
     * @return array{0: float, 1: float}
     */
    public static function rates(string $provider, string $modelName): array
    {
        $pricing = config('ai.pricing', []);
        $providerRates = is_array($pricing[$provider] ?? null) ? $pricing[$provider] : [];

        $match = null;
        foreach ($providerRates as $pattern => $rates) {
            if ($pattern === 'default' || ! is_array($rates)) {
                continue;
            }
            if (strcasecmp((string) $pattern, $modelName) === 0) {
                $match = $rates;
                break;
            }
        }

        if ($match === null) {
            foreach ($providerRates as $pattern => $rates) {
                if ($pattern === 'default' || ! is_array($rates)) {
                    continue;
                }
                if (str_contains(strtolower($modelName), strtolower((string) $pattern))) {
                    $match = $rates;
                    break;
                }
            }
        }

        if ($match === null) {
            $match = is_array($providerRates['default'] ?? null)
                ? $providerRates['default']
                : (is_array($pricing['default'] ?? null) ? $pricing['default'] : ['input' => 0.15, 'output' => 0.60]);
        }

        return [
            (float) ($match['input'] ?? 0.15),
            (float) ($match['output'] ?? 0.60),
        ];
    }
}
