<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Services\LocaleRegistry;
use App\Services\TranslationFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TranslationController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        private TranslationFileService $translations,
        private LocaleRegistry $locales,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(config('kit.features.translations_ui'), 404);

        $this->authorizePermission($request, 'settings.manage');

        $locale = $request->string('locale')->toString() ?: app()->getLocale();
        $available = $this->locales->codes();

        if (! in_array($locale, $available, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        $search = $request->string('search')->trim()->toString() ?: null;

        return Inertia::render('settings/Translations/Index', [
            'entries' => $this->translations->paginate(
                $locale,
                $search,
                max(1, $request->integer('page', 1)),
                25,
            ),
            'editingLocale' => $locale,
            'filters' => [
                'search' => $search,
                'locale' => $locale,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('kit.features.translations_ui'), 404);

        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,5}(-[a-z]{2,8})?$/'],
            'name' => ['required', 'string', 'max:100'],
            'native' => ['required', 'string', 'max:100'],
            'dir' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
            'from' => ['required', 'string', Rule::in($this->locales->codes())],
        ]);

        try {
            $this->translations->createLocale(
                $validated['code'],
                [
                    'name' => $validated['name'],
                    'native' => $validated['native'],
                    'dir' => $validated['dir'],
                ],
                $validated['from'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.translations.index', ['locale' => strtolower($validated['code'])])
            ->with('success', __('Language added.'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(config('kit.features.translations_ui'), 404);

        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->locales->codes())],
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string'],
        ]);

        $this->translations->updateValues(
            $validated['locale'],
            array_map(fn ($v) => (string) ($v ?? ''), $validated['translations']),
        );

        return back()->with('success', __('Translations saved.'));
    }
}
