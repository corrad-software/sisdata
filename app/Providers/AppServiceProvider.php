<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\ThemeColor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        View::composer(['layouts.guest', 'layouts.app', 'layouts.admin', 'livewire.layout.navigation'], function ($view) {
            try {
                $siteTitle = Setting::get('site_title', config('app.name', 'Laravel'));
                $primaryColor = Setting::get('primary_color');
                $siteFavicon = Setting::get('site_favicon');
            } catch (\Throwable) {
                $siteTitle = config('app.name', 'Laravel');
                $primaryColor = null;
                $siteFavicon = null;
            }

            $themeColorCss = $primaryColor ? ThemeColor::css($primaryColor) : null;

            $view->with([
                'siteTitle' => $siteTitle,
                'siteFaviconUrl' => $siteFavicon ? asset('storage/'.$siteFavicon) : null,
                'themeColorCss' => $themeColorCss,
            ]);
        });
    }
}
