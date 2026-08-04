<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class SiteSettings extends Component
{
    use WithFileUploads;

    public string $siteTitle = '';

    public string $footerText = '';

    public string $primaryColor = '#2563EB';

    public $logo;

    public $favicon;

    public ?string $currentLogoPath = null;

    public ?string $currentFaviconPath = null;

    public function mount(): void
    {
        $this->siteTitle = Setting::get('site_title', config('app.name'));
        $this->footerText = Setting::get('footer_text', '');
        $this->primaryColor = Setting::get('primary_color', '#2563EB');
        $this->currentLogoPath = Setting::get('site_logo');
        $this->currentFaviconPath = Setting::get('site_favicon');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'siteTitle' => ['required', 'string', 'max:100'],
            'footerText' => ['nullable', 'string', 'max:255'],
            'primaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'image', 'max:512'],
        ]);

        Setting::set('site_title', $validated['siteTitle']);
        Setting::set('footer_text', $validated['footerText'] ?: null);
        Setting::set('primary_color', strtoupper($validated['primaryColor']));

        if ($this->logo) {
            if ($this->currentLogoPath) {
                Storage::disk('public')->delete($this->currentLogoPath);
            }

            $path = $this->logo->store('branding', 'public');
            Setting::set('site_logo', $path);
            $this->currentLogoPath = $path;
            $this->logo = null;
        }

        if ($this->favicon) {
            if ($this->currentFaviconPath) {
                Storage::disk('public')->delete($this->currentFaviconPath);
            }

            $path = $this->favicon->store('branding', 'public');
            Setting::set('site_favicon', $path);
            $this->currentFaviconPath = $path;
            $this->favicon = null;
        }

        $this->dispatch('toast', type: 'success', message: __('Settings saved successfully.'));
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        Setting::set('site_logo', null);
        $this->currentLogoPath = null;

        $this->dispatch('toast', type: 'success', message: __('Logo removed.'));
    }

    public function removeFavicon(): void
    {
        if ($this->currentFaviconPath) {
            Storage::disk('public')->delete($this->currentFaviconPath);
        }

        Setting::set('site_favicon', null);
        $this->currentFaviconPath = null;

        $this->dispatch('toast', type: 'success', message: __('Favicon removed.'));
    }

    public function resetColor(): void
    {
        $this->primaryColor = '#2563EB';
        Setting::set('primary_color', $this->primaryColor);

        $this->dispatch('toast', type: 'success', message: __('Theme colour reset to default.'));
    }

    public function resetFooterText(): void
    {
        $this->footerText = '';
        Setting::set('footer_text', null);

        $this->dispatch('toast', type: 'success', message: __('Footer text reset to default.'));
    }

    public function render()
    {
        return view('livewire.site-settings')
            ->layout('layouts.admin', [
                'breadcrumbs' => [
                    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
                    ['label' => __('Settings')],
                ],
            ]);
    }
}
