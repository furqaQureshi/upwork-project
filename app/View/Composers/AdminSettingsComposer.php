<?php

namespace App\View\Composers;

use App\Services\Admin\SettingsIndexViewData;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminSettingsComposer
{
    public function __construct(private readonly SettingsIndexViewData $viewData)
    {
    }

    public function compose(View $view): void
    {
        $settings = $view->getData()['settings'] ?? collect();
        if (! $settings instanceof Collection) {
            $settings = collect($settings);
        }

        $view->with($this->viewData->toArray($settings));
    }
}
