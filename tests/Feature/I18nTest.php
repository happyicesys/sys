<?php

namespace Tests\Feature;

use App\Models\UiTranslation;
use App\Models\User;
use App\Services\I18n\TextScanner;
use App\Services\I18n\TranslationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * English / Simplified Chinese UI (Brian, 2026-10-26), Bahasa Indonesia since 2026-10-30. Pinned: every text
 * the code marks has a shipped wording in each language; each user's choice is kept on their account; a
 * person's wording (Translations page or the in-page editor) wins over the shipped one at once for the pages
 * and for `__()`; only `update translations` may change wording; the deep scan finds unmarked text.
 */
class I18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_every_marked_text_has_a_shipped_wording_in_every_language(): void
    {
        $used = TextScanner::forApp()->keys();
        $this->assertSame(['zh_CN', 'id'], TranslationCatalog::translated());
        foreach (TranslationCatalog::translated() as $locale) {
            $missing = array_keys(array_diff_key($used, app(TranslationCatalog::class)->base($locale)));

            $this->assertSame([], $missing, "Run `php artisan i18n:scan --sync` and translate these in lang/{$locale}.json");
        }
    }

    public function test_a_user_picks_their_language_and_only_a_known_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/user/locale', ['locale' => 'zh_CN'])->assertNoContent();
        $this->assertSame('zh_CN', $user->fresh()->locale);

        $this->actingAs($user)->postJson('/user/locale', ['locale' => 'id'])->assertNoContent();
        $this->assertSame('id', $user->fresh()->locale, 'Bahasa Indonesia (Brian, 2026-10-30)');

        $this->actingAs($user)->postJson('/user/locale', ['locale' => 'fr'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/user/locale', ['locale' => 'en'])->assertNoContent();
        $this->assertNull($user->fresh()->locale, 'English is the default, stored as NULL');
    }

    public function test_a_persons_wording_wins_over_the_shipped_one_everywhere(): void
    {
        $catalog = app(TranslationCatalog::class);
        $before = $catalog->version('zh_CN');
        $this->assertSame('保存', $catalog->dictionary('zh_CN')['Save']);

        $admin = $this->user(['read translations', 'update translations']);
        $this->actingAs($admin)->putJson('/translations', ['locale' => 'zh_CN', 'key' => 'Save', 'value' => '存储'])
            ->assertOk()->assertJson(['key' => 'Save', 'value' => '存储']);

        $this->assertSame('存储', $catalog->dictionary('zh_CN')['Save']);
        $this->assertNotSame($before, $catalog->version('zh_CN'), 'a new version makes browsers fetch the new wording');
        $this->assertSame($admin->id, UiTranslation::first()->updated_by);

        // The pages' dictionary, and the server's own __().
        $this->actingAs($admin)->getJson('/i18n/zh_CN.json?v='.$catalog->version('zh_CN'))
            ->assertOk()->assertJsonPath('Save', '存储')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');
        app()->setLocale('zh_CN');
        app('translator')->setLoaded([]);
        $this->assertSame('存储', __('Save'));

        // English wording can be changed too.
        $this->actingAs($admin)->putJson('/translations', ['locale' => 'en', 'key' => 'Sites', 'value' => 'Locations'])->assertOk();
        $this->assertSame(['Sites' => 'Locations'], $catalog->dictionary('en'));

        // Reset: back to the shipped wording.
        $this->actingAs($admin)->putJson('/translations', ['locale' => 'zh_CN', 'key' => 'Save', 'value' => ''])
            ->assertOk()->assertJson(['value' => '保存']);
        $this->assertSame(1, UiTranslation::count());
    }

    public function test_only_those_allowed_may_open_or_change_wording(): void
    {
        $viewer = $this->user();
        $this->actingAs($viewer)->get('/translations')->assertForbidden();
        $this->actingAs($viewer)->putJson('/translations', ['locale' => 'zh_CN', 'key' => 'Save', 'value' => 'x'])->assertForbidden();
        $this->assertSame(0, UiTranslation::count());

        $reader = $this->user(['read translations']);
        $this->actingAs($reader)->get('/translations?search=Save')->assertOk();
        $this->actingAs($reader)->putJson('/translations', ['locale' => 'zh_CN', 'key' => 'Save', 'value' => 'x'])->assertForbidden();
    }

    public function test_the_page_is_told_its_language_and_dictionary_version(): void
    {
        $user = $this->user(['read translations']);
        $user->forceFill(['locale' => 'zh_CN'])->save();
        $version = app(TranslationCatalog::class)->version('zh_CN');

        $this->actingAs($user)->get('/translations')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('i18n.locale', 'zh_CN')
            ->where('i18n.version', $version)
            ->where('i18n.locales.zh_CN', '简体中文'));
        $this->assertSame('zh_CN', app()->getLocale(), 'server messages follow the user too');
    }

    /**
     * Brian, 2026-10-26: name a place the way a person finds it — menu section › entry › page title ·
     * web address — never a file name.
     */
    public function test_each_text_says_where_it_is_shown_in_words_a_person_knows(): void
    {
        $places = app(\App\Services\I18n\TranslationPlaces::class);
        $this->assertContains('Menu & top bar (every page)', $places->for('Machine Health'), 'a menu entry is listed under the menu');
        $this->assertContains('Admin › Translations · /translations', $places->for('Shown on:'), 'a page: section › entry · address');
        $this->assertContains('Menu & top bar (every page)', $places->for('Shown on:'), 'the Edit wording box sits in the top bar');
        $this->assertContains('Machine Management › Machines Settings › VM Edit · /settings/vend/{id}/update', $places->for('Reset fan detection'));
        $this->assertContains('System message shown in Operations › Dashboard · /vends/customers', $places->for('Touch screen frozen'));
        foreach ($places->pages() as $info) {
            $this->assertDoesNotMatchRegularExpression('/\\.vue|resources\\/|\\b[A-Z][a-zA-Z]+\\/[A-Z][a-zA-Z]+\\b/', $info['label'], 'no file names: '.$info['label']);
        }
        app()->setLocale('zh_CN');
        $this->assertContains('系统管理 › 翻译 · /translations', $places->for('Shown on:'), 'names follow the reader\'s language');
        app()->setLocale('en');

        $reader = $this->user(['read translations']);
        $this->actingAs($reader)->getJson('/translations/places?key=Shown%20on%3A')
            ->assertOk()->assertJsonFragment(['Admin › Translations · /translations']);
        $this->actingAs($reader)->get('/translations?page_name=layout')
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.data', fn ($rows) => collect($rows)->every(fn ($r) => in_array('layout', $r['place_ids'], true)))
            ->where('entries.total', fn ($total) => $total > 50)
            ->where('filters.page_name', 'layout')
            ->where('pages.layout.label', 'Menu & top bar (every page)'));
        $this->actingAs($this->user())->getJson('/translations/places?key=Save')->assertForbidden();
    }

    public function test_the_deep_scan_finds_text_that_is_not_marked(): void
    {
        $dir = storage_path('framework/testing/i18n-scan');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir.'/resources/js/Pages');
        File::put($dir.'/resources/js/Pages/Sample.vue', <<<'VUE'
<template>
    <h1>Machine Settings</h1>
    <h2>{{ $t('Already marked') }}</h2>
    <input placeholder="Search site" />
    <MultiSelect label="desc" />
    <span v-tooltip="ready ? 'Ready to go' : $t('Waiting')">{{ ok ? 'Yes' : $t('No') }}</span>
    <!-- <p>Commented out</p> -->
</template>
<script setup>
toast.success('Saved the machine')
if (!confirm('Delete this site?')) return
toast.error(t('Marked toast'))
const options = [{ id: 'all', name: 'All machines' }]
</script>
VUE);
        File::ensureDirectoryExists($dir.'/app/Http');
        File::put($dir.'/app/Http/Sample.php', "<?php\nreturn back()->with('success', 'Machine saved');\n// ->with('error', 'Not this one')\n");

        $scanner = new TextScanner($dir);
        $texts = collect($scanner->hardcoded())->flatten(1)->pluck('text')->all();

        foreach (['Machine Settings', 'Search site', 'Ready to go', 'Yes', 'Saved the machine', 'Delete this site?', 'All machines', 'Machine saved'] as $expected) {
            $this->assertContains($expected, $texts);
        }
        foreach (['Already marked', 'Waiting', 'No', 'Marked toast', 'desc', 'Commented out', 'Not this one'] as $absent) {
            $this->assertNotContains($absent, $texts);
        }
        $this->assertSame(['Already marked', 'Marked toast', 'No', 'Waiting'], array_keys($scanner->keys()));
        File::deleteDirectory($dir);
    }
}
