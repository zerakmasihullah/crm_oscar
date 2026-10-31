<?php

namespace App\Http\Controllers;

use App\Services\LocaleRegistry;
use App\Support\SystemControl;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function update(string $locale, LocaleRegistry $locales): RedirectResponse
    {
        if (! SystemControl::multilingualEnabled()) {
            abort(404);
        }

        if (! $locales->exists($locale)) {
            abort(404);
        }

        return back()
            ->cookie('locale', $locale, 60 * 24 * 365);
    }
}
