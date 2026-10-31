<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_cookie_can_be_set_for_supported_locales(): void
    {
        foreach (['en', 'fa', 'ps'] as $locale) {
            $response = $this->from('/')->post(route('locale.update', $locale));

            $response->assertRedirect('/');
            $response->assertCookie('locale', $locale, false);
        }
    }

    public function test_unsupported_locale_returns_not_found(): void
    {
        $this->from('/')->post(route('locale.update', 'xx'))->assertNotFound();
    }

    public function test_arabic_and_urdu_are_not_available_locales(): void
    {
        $this->from('/')->post(route('locale.update', 'ar'))->assertNotFound();
        $this->from('/')->post(route('locale.update', 'ur'))->assertNotFound();
    }

    public function test_login_shares_en_fa_ps_locales_only(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('locales')
            ->where('locales', function ($locales) {
                $codes = collect($locales)->pluck('code')->sort()->values()->all();

                return $codes === ['en', 'fa', 'ps'];
            })
        );
    }
}
