<?php

namespace App\Ai;

use App\Support\AiSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Ai\Contracts\Tool;

class AgentToolRegistry
{
    /**
     * @var array<class-string<Tool>, 'read'|'create'|'update'|'delete'|'analytics'>
     */
    protected static array $tools = [];

    /**
     * Register tools with an impact level.
     *
     * @param  class-string<Tool>|array<int|class-string<Tool>, class-string<Tool>|'read'|'create'|'update'|'delete'|'analytics'>  $tools
     * @param  'read'|'create'|'update'|'delete'|'analytics'  $impact  Used when $tools is a single class or a list of classes
     */
    public static function register(string|array $tools, string $impact = 'create'): void
    {
        if (is_string($tools)) {
            self::$tools[$tools] = self::normalizeImpact($impact);

            return;
        }

        foreach ($tools as $key => $value) {
            if (is_string($key) && in_array($value, ['read', 'create', 'update', 'delete', 'analytics'], true)) {
                self::$tools[$key] = $value;

                continue;
            }

            if (is_string($value) && ! in_array($value, ['read', 'create', 'update', 'delete', 'analytics'], true)) {
                self::$tools[$value] = self::normalizeImpact($impact);
            }
        }
    }

    /**
     * @return list<class-string<Tool>>
     */
    public static function classes(): array
    {
        return array_keys(self::$tools);
    }

    /**
     * Tools allowed by org settings.
     *
     * @return list<Tool>
     */
    public static function make(?Authenticatable $user = null): array
    {
        $allowCreate = AiSettings::chatAllowCreate();
        $allowDelete = AiSettings::chatAllowDelete();
        $allowAnalytics = AiSettings::chatAllowAnalytics();
        $chatOn = AiSettings::chatEnabled();

        $instances = [];

        foreach (self::$tools as $class => $impact) {
            $allowed = match ($impact) {
                'read' => $chatOn,
                'create', 'update' => $allowCreate,
                'delete' => $allowDelete,
                'analytics' => $allowAnalytics,
                default => false,
            };

            if (! $allowed) {
                continue;
            }

            $instances[] = app()->make($class, ['user' => $user]);
        }

        return $instances;
    }

    /**
     * @internal Tests only.
     */
    public static function flush(): void
    {
        self::$tools = [];
    }

    /**
     * @return 'read'|'create'|'update'|'delete'|'analytics'
     */
    protected static function normalizeImpact(string $impact): string
    {
        return match ($impact) {
            'read' => 'read',
            'update' => 'update',
            'delete' => 'delete',
            'analytics' => 'analytics',
            default => 'create',
        };
    }
}
