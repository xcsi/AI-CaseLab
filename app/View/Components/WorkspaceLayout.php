<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class WorkspaceLayout extends Component
{
    public function __construct(
        public string $title,
        public string $exitUrl,
        public string $startedAt,
        public string $diagnosisUrl,
        public int $evidenceViewedCount = 0,
        public int $evidenceTotalCount = 0,
        /**
         * Engineering Discussion entry point (Version 2, Phase 18
         * Milestone 1) — null when the case doesn't have
         * discussion_enabled, in which case the workspace renders exactly
         * as it always has for every existing Version 1 case.
         */
        public ?string $discussionUrl = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.workspace');
    }
}
