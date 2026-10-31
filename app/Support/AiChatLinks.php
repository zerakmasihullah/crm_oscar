<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Markdown links for General Chat tool results (rendered as clickable Inertia links in the panel).
 */
class AiChatLinks
{
    public static function markdown(string $label, string $url): string
    {
        $safeLabel = str_replace(['[', ']', '(', ')'], ['(', ')', '', ''], $label);

        return "[{$safeLabel}]({$url})";
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function forModel(Model $model, string $label, string $routeName, mixed $routeParam = null): string
    {
        $param = $routeParam ?? $model;

        return self::markdown($label, route($routeName, $param, absolute: false));
    }
}
