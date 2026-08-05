<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Support\ThemeColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function show()
    {
        // Setting::get()'s default only applies when the key is entirely
        // absent from the DB — a row that exists with a null value (e.g.
        // after being explicitly cleared) falls through as null, not the
        // default, so `?:` is needed on top to guarantee a valid color.
        $primaryColor = Setting::get('primary_color') ?: '#2563EB';
        $siteLogo = Setting::get('site_logo');
        $siteFavicon = Setting::get('site_favicon');

        return response()->json([
            'data' => [
                'site_title' => Setting::get('site_title') ?: config('app.name', 'MYDS-Laravel'),
                'footer_text' => Setting::get('footer_text'),
                'primary_color' => $primaryColor,
                'site_logo_url' => $siteLogo ? asset('storage/'.$siteLogo) : null,
                'site_favicon_url' => $siteFavicon ? asset('storage/'.$siteFavicon) : null,
                'theme_css' => ThemeColor::css($primaryColor),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $validated = $request->validated();

        Setting::set('site_title', $validated['site_title']);
        Setting::set('footer_text', $validated['footer_text'] ?: null);
        Setting::set('primary_color', strtoupper($validated['primary_color']));

        return $this->show();
    }

    public function uploadLogo(Request $request)
    {
        return $this->uploadBrandingImage($request, 'site_logo', maxKilobytes: 1024);
    }

    public function uploadFavicon(Request $request)
    {
        return $this->uploadBrandingImage($request, 'site_favicon', maxKilobytes: 512);
    }

    public function deleteLogo()
    {
        return $this->deleteBrandingImage('site_logo');
    }

    public function deleteFavicon()
    {
        return $this->deleteBrandingImage('site_favicon');
    }

    protected function uploadBrandingImage(Request $request, string $settingKey, int $maxKilobytes)
    {
        $request->validate([
            'file' => ['required', 'image', "max:{$maxKilobytes}"],
        ]);

        $currentPath = Setting::get($settingKey);

        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        $path = $request->file('file')->store('branding', 'public');
        Setting::set($settingKey, $path);

        return $this->show();
    }

    protected function deleteBrandingImage(string $settingKey)
    {
        $currentPath = Setting::get($settingKey);

        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        Setting::set($settingKey, null);

        return $this->show();
    }
}
