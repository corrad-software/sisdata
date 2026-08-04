<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use App\Support\ThemeColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_publicly_readable(): void
    {
        $response = $this->getJson('/api/settings')->assertOk();

        $response->assertJsonPath('data.site_title', config('app.name'));
        $response->assertJsonPath('data.site_logo_url', null);
        $response->assertJsonPath('data.site_favicon_url', null);
        $response->assertJsonStructure(['data' => ['site_title', 'footer_text', 'primary_color', 'site_logo_url', 'site_favicon_url', 'theme_css']]);
    }

    public function test_settings_fall_back_to_defaults_when_explicitly_cleared_to_null(): void
    {
        // A row can exist with a null value (e.g. after being cleared) rather
        // than being entirely absent — Setting::get()'s default param only
        // covers the "absent" case, so the controller must guard against this
        // separately or it 500s trying to build theme CSS from a null color.
        Setting::set('primary_color', null);
        Setting::set('site_title', null);

        $response = $this->getJson('/api/settings')->assertOk();

        $response->assertJsonPath('data.primary_color', '#2563EB');
        $response->assertJsonPath('data.site_title', config('app.name'));
        $response->assertJsonPath('data.theme_css', ThemeColor::css('#2563EB'));
    }

    public function test_theme_css_reflects_the_stored_primary_color(): void
    {
        Setting::set('primary_color', '#123ABC');

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.theme_css', ThemeColor::css('#123ABC'));
    }

    public function test_guests_cannot_update_settings(): void
    {
        $this->putJson('/api/settings', [
            'site_title' => 'New Title',
            'primary_color' => '#123ABC',
        ])->assertUnauthorized();
    }

    public function test_non_admin_users_cannot_update_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->putJson('/api/settings', [
            'site_title' => 'New Title',
            'primary_color' => '#123ABC',
        ])->assertForbidden();
    }

    public function test_admin_can_update_settings_and_cache_is_busted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->putJson('/api/settings', [
            'site_title' => 'New Title',
            'footer_text' => 'Custom footer',
            'primary_color' => '#123abc',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.site_title', 'New Title')
            ->assertJsonPath('data.footer_text', 'Custom footer')
            ->assertJsonPath('data.primary_color', '#123ABC');

        $this->assertSame('New Title', Setting::get('site_title'));
        $this->assertSame('#123ABC', Setting::get('primary_color'));
    }

    public function test_update_validates_hex_color_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->putJson('/api/settings', [
            'site_title' => 'New Title',
            'primary_color' => 'not-a-hex-color',
        ])->assertUnprocessable()->assertJsonValidationErrors('primary_color');
    }

    public function test_admin_can_upload_and_replace_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $first = UploadedFile::fake()->image('logo.png');
        $this->actingAs($admin)
            ->postJson('/api/settings/logo', ['file' => $first])
            ->assertOk();

        $firstPath = Setting::get('site_logo');
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('logo-2.png');
        $this->actingAs($admin)
            ->postJson('/api/settings/logo', ['file' => $second])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists(Setting::get('site_logo'));
    }

    public function test_admin_can_delete_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/settings/logo', [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $path = Setting::get('site_logo');

        $this->actingAs($admin)->deleteJson('/api/settings/logo')->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Setting::get('site_logo'));
    }

    public function test_logo_upload_rejects_oversized_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/settings/logo', [
                'file' => UploadedFile::fake()->image('logo.png')->size(2048),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_theme_color_ramp_produces_eleven_shades_as_rgb_triplets(): void
    {
        $ramp = ThemeColor::ramp('#2563EB');

        $this->assertCount(11, $ramp);
        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($ramp));

        foreach ($ramp as $rgb) {
            $this->assertMatchesRegularExpression('/^\d{1,3} \d{1,3} \d{1,3}$/', $rgb);
        }
    }
}
