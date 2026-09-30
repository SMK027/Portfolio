<?php

namespace App\View\Components;

use App\Models\Page;
use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?Page $page = null,
        public ?string $image = null,
        public string $type = 'website',
    ) {
    }

    public function render(): View
    {
        return view('layouts.public');
    }
}
