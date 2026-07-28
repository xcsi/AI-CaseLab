<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class WorkspaceLayout extends Component
{
    public function __construct(
        public string $title,
        public string $exitUrl,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.workspace');
    }
}
