<?php

namespace App\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

trait RendersPrintView
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function printView(string $view, array $data = [], int $status = 200): Response|View
    {
        abort_unless(config('kit.features.exports'), 404);

        return response()->view($view, $data, $status);
    }
}
