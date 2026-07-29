<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AdminLayout extends Component
{
    /**
     * @param  array<int, array{label: string, url?: string}>  $breadcrumbs  Trail excluding "Home"; the last item is the page title.
     */
    public function __construct(public array $breadcrumbs = [])
    {
        //
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.admin');
    }
}
