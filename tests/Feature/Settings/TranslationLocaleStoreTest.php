<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\LocaleRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use App\Models\Role;
use Tests\TestCase;

class TranslationLocaleStoreTest extends TestCase
{
    use RefreshDatabase;

    private string $testCode = 'zz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cleanupTestLocale();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestLocale();
        parent::tearDown();
    }

    public function test_owner_can_add_language_by_duplicating_english_with_rtl(): void
    {
        config(['kit.features.translations_ui' => true]);

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $this->actingAs($owner)
            ->post(route('settings.translations.store'), [
                'code' => $this->testCode,
                'name' => 'Test Language',
                'native' => 'زبان آزمایشی',
                'dir' => 'rtl',
                'from' => 'en',
            ])
            ->assertRedirect(route('settings.translations.index', ['locale' => $this->testCode]));

        $this->assertFileExists(lang_path("{$this->testCode}.json"));
        $this->assertTrue(app(LocaleRegistry::class)->exists($this->testCode));
        $this->assertSame('rtl', app(LocaleRegistry::class)->direction($this->testCode));

        $english = json_decode((string) File::get(lang_path('en.json')), true);
        $created = json_decode((string) File::get(lang_path("{$this->testCode}.json")), true);

        $this->assertIsArray($english);
        $this->assertIsArray($created);
        $this->assertSame(array_keys($english), array_keys($created));

        $this->from('/')
            ->post(route('locale.update', $this->testCode))
            ->assertRedirect('/')
            ->assertCookie('locale', $this->testCode, false);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locales', fn ($locales) => collect($locales)->contains(
                    fn ($item) => ($item['code'] ?? null) === $this->testCode
                        && ($item['dir'] ?? null) === 'rtl'
                        && ($item['native'] ?? null) === 'زبان آزمایشی'
                ))
            );
    }

    public function test_duplicate_language_code_is_rejected(): void
    {
        config(['kit.features.translations_ui' => true]);

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $this->actingAs($owner)
            ->post(route('settings.translations.store'), [
                'code' => 'fa',
                'name' => 'Dari Again',
                'native' => 'دری',
                'dir' => 'rtl',
                'from' => 'en',
            ])
            ->assertSessionHasErrors('code');
    }

    private function cleanupTestLocale(): void
    {
        $json = lang_path("{$this->testCode}.json");

        if (File::exists($json)) {
            File::delete($json);
        }

        $registry = lang_path('locales.json');

        if (! File::exists($registry)) {
            return;
        }

        $data = json_decode((string) File::get($registry), true);

        if (! is_array($data) || ! array_key_exists($this->testCode, $data)) {
            return;
        }

        unset($data[$this->testCode]);

        if ($data === []) {
            File::delete($registry);

            return;
        }

        File::put(
            $registry,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
