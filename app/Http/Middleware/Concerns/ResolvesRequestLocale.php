<?php

namespace App\Http\Middleware\Concerns;

use App\Models\OrgSetting;
use App\Services\LocaleRegistry;
use App\Support\SystemControl;
use Illuminate\Http\Request;

trait ResolvesRequestLocale
{
    protected function resolveLocale(Request $request): string
    {
        $available = app(LocaleRegistry::class)->codes();
        $orgDefault = (string) OrgSetting::getValue('default_locale', config('app.locale'));
        $fallback = in_array($orgDefault, $available, true)
            ? $orgDefault
            : (string) config('app.fallback_locale');

        if (! SystemControl::multilingualEnabled()) {
            return $fallback;
        }

        $locale = $request->cookie('locale', $fallback);

        if (! in_array($locale, $available, true)) {
            $locale = $fallback;
        }

        return $locale;
    }
}
