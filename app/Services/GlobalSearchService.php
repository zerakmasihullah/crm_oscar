<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\FieldNote;
use App\Models\Role;
use App\Models\User;
use App\Support\EfficientQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GlobalSearchService
{
    private const PER_GROUP = 6;

    private const MIN_RECORD_QUERY = 2;

    /**
     * @return list<array{key: string, label: string, items: list<array{id: string, title: string, subtitle: ?string, href: string, type: string}>}>
     */
    public function search(User $user, string $query): array
    {
        $query = trim($query);
        $groups = [];

        $pages = $this->searchPages($user, $query);

        if ($pages->isNotEmpty()) {
            $groups[] = [
                'key' => 'pages',
                'label' => __('Pages'),
                'items' => $pages->all(),
            ];
        }

        if (mb_strlen($query) < self::MIN_RECORD_QUERY) {
            return $groups;
        }

        if ($user->can('users.view')) {
            $users = $this->searchUsers($query);

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'key' => 'users',
                    'label' => __('Users'),
                    'items' => $users->all(),
                ];
            }
        }

        if ($user->can('roles.view')) {
            $roles = $this->searchRoles($query);

            if ($roles->isNotEmpty()) {
                $groups[] = [
                    'key' => 'roles',
                    'label' => __('Roles'),
                    'items' => $roles->all(),
                ];
            }
        }

        if ($user->can('field_notes.view')) {
            $notes = $this->searchFieldNotes($user, $query);

            if ($notes->isNotEmpty()) {
                $groups[] = [
                    'key' => 'field_notes',
                    'label' => __('Field notes'),
                    'items' => $notes->all(),
                ];
            }
        }

        if (
            $user->can('backups.view')
            || $user->can('backups.run')
            || $user->can('backups.download')
        ) {
            $backups = $this->searchBackups($query);

            if ($backups->isNotEmpty()) {
                $groups[] = [
                    'key' => 'backups',
                    'label' => __('Backups'),
                    'items' => $backups->all(),
                ];
            }
        }

        return $groups;
    }

    /**
     * @return Collection<int, array{id: string, title: string, subtitle: ?string, href: string, type: string}>
     */
    private function searchPages(User $user, string $query): Collection
    {
        $pages = collect($this->pageCatalog($user));

        if ($query !== '') {
            $needle = Str::lower($query);

            $pages = $pages->filter(function (array $page) use ($needle): bool {
                $haystack = Str::lower(implode(' ', [
                    $page['title'],
                    $page['subtitle'] ?? '',
                    $page['href'],
                    implode(' ', $page['keywords'] ?? []),
                ]));

                return str_contains($haystack, $needle);
            });
        }

        return $pages
            ->take(self::PER_GROUP)
            ->map(fn (array $page): array => $this->item(
                id: 'page:'.$page['href'],
                title: $page['title'],
                subtitle: $page['subtitle'] ?? __('Page'),
                href: $page['href'],
                type: 'page',
            ))
            ->values();
    }

    /**
     * @return list<array{title: string, subtitle?: string, href: string, keywords?: list<string>}>
     */
    private function pageCatalog(User $user): array
    {
        $pages = [
            [
                'title' => __('Dashboard'),
                'subtitle' => __('Navigation'),
                'href' => '/dashboard',
                'keywords' => ['home', 'overview'],
            ],
        ];

        if ($user->can('field_notes.view')) {
            $pages[] = [
                'title' => __('Field notes'),
                'subtitle' => __('Navigation'),
                'href' => '/field-notes',
                'keywords' => ['notes', 'field'],
            ];
        }

        if ($user->can('orders.view')) {
            $pages[] = [
                'title' => __('Orders'),
                'subtitle' => __('Orders & Sales'),
                'href' => '/orders',
                'keywords' => ['orders', 'sales', 'pending'],
            ];
        }

        if ($user->can('orders.create')) {
            $pages[] = [
                'title' => __('Create order'),
                'subtitle' => __('Orders & Sales'),
                'href' => '/orders/create',
                'keywords' => ['create order', 'new order'],
            ];
            $pages[] = [
                'title' => __('Create deal'),
                'subtitle' => __('Orders & Sales'),
                'href' => '/orders/create-deal',
                'keywords' => ['deal', 'toppings'],
            ];
        }

        if ($user->can('menus.view')) {
            $pages[] = [
                'title' => __('Menus'),
                'subtitle' => __('Store'),
                'href' => '/menus',
                'keywords' => ['menu', 'categories'],
            ];
        }

        if ($user->can('menu_items.view')) {
            $pages[] = [
                'title' => __('Menu items'),
                'subtitle' => __('Store'),
                'href' => '/menu-items',
                'keywords' => ['items', 'products', 'food'],
            ];
        }

        if ($user->can('platform_revenues.view')) {
            $pages[] = [
                'title' => __('Platform revenue'),
                'subtitle' => __('Finance'),
                'href' => '/platform-revenues',
                'keywords' => ['platform', 'revenue', 'uber', 'deliveroo', 'external sales'],
            ];
        }

        if ($user->can('expenses.view')) {
            $pages[] = [
                'title' => __('Expenses'),
                'subtitle' => __('Finance'),
                'href' => '/expenses',
                'keywords' => ['expense', 'cost', 'spend', 'outgoing'],
            ];
        }

        if ($user->can('platform_revenues.view') || $user->can('expenses.view')) {
            $pages[] = [
                'title' => __('Finance reports'),
                'subtitle' => __('Finance'),
                'href' => '/finance/reports',
                'keywords' => ['report', 'finance', 'net', 'profit'],
            ];
        }

        if ($user->can('platforms.view')) {
            $pages[] = [
                'title' => __('Sales platforms'),
                'subtitle' => __('Settings'),
                'href' => '/settings/platforms',
                'keywords' => ['platform', 'sales channel', 'uber', 'deliveroo'],
            ];
        }

        if ($user->can('discounts.view')) {
            $pages[] = [
                'title' => __('Discounts'),
                'subtitle' => __('Marketing'),
                'href' => '/discounts',
                'keywords' => ['discount', 'promo code'],
            ];
        }

        if ($user->can('promotions.view')) {
            $pages[] = [
                'title' => __('Promotions'),
                'subtitle' => __('Marketing'),
                'href' => '/promotions',
                'keywords' => ['email', 'campaign'],
            ];
        }

        if ($user->can('promotion_recipients.view')) {
            $pages[] = [
                'title' => __('Promotion recipients'),
                'subtitle' => __('Marketing'),
                'href' => '/promotion-recipients',
                'keywords' => ['recipients', 'tracking'],
            ];
        }

        if ($user->can('oscar_users.view')) {
            $pages[] = [
                'title' => __('Customers'),
                'subtitle' => __('Navigation'),
                'href' => '/oscar-users',
                'keywords' => ['customers', 'customer'],
            ];
        }

        if ($user->can('chats.view')) {
            $pages[] = [
                'title' => __('Chat'),
                'subtitle' => __('Navigation'),
                'href' => '/chats',
                'keywords' => ['messages', 'customer chat'],
            ];
        }

        if ($user->can('deliveries.view')) {
            $pages[] = [
                'title' => __('Delivery'),
                'subtitle' => __('Store'),
                'href' => '/deliveries',
                'keywords' => ['delivery', 'fees', 'store'],
            ];
        }

        if ($user->can('reservations.view')) {
            $pages[] = [
                'title' => __('Reservations'),
                'subtitle' => __('Store'),
                'href' => '/reservations',
                'keywords' => ['booking', 'table', 'store'],
            ];
        }

        if ($user->can('analytics.view')) {
            $pages[] = [
                'title' => __('Analytics'),
                'subtitle' => __('Marketing'),
                'href' => '/analytics',
                'keywords' => ['reports', 'revenue', 'charts'],
            ];
        }

        if ($user->can('ai.view') || $user->can('ai.analytics')) {
            $pages[] = [
                'title' => __('AI Analytics'),
                'subtitle' => __('Navigation'),
                'href' => '/ai/analytics',
                'keywords' => ['ai', 'analytics', 'reports'],
            ];
        }

        $canOpenSettings =
            $user->can('settings.manage')
            || $user->can('users.view')
            || $user->can('roles.view')
            || $user->can('backups.view')
            || $user->can('backups.run')
            || $user->can('backups.download')
            || $user->can('storage.view')
            || $user->can('trash.view')
            || $user->can('audit.view');

        if ($canOpenSettings) {
            $pages[] = [
                'title' => __('Settings'),
                'subtitle' => __('Navigation'),
                'href' => $this->settingsEntryHref($user),
                'keywords' => ['settings', 'preferences', 'admin'],
            ];
        }

        foreach ($this->settingsPages($user) as $page) {
            $pages[] = $page;
        }

        return $pages;
    }

    private function settingsEntryHref(User $user): string
    {
        if ($user->can('settings.manage')) {
            return '/settings/appearance';
        }

        if ($user->can('users.view')) {
            return '/settings/users';
        }

        if ($user->can('roles.view')) {
            return '/settings/roles';
        }

        if (
            $user->can('backups.view')
            || $user->can('backups.run')
            || $user->can('backups.download')
        ) {
            return '/settings/backups';
        }

        if ($user->can('storage.view')) {
            return '/settings/file-manager';
        }

        if ($user->can('trash.view')) {
            return '/settings/trash';
        }

        if ($user->can('audit.view')) {
            return '/settings/login-logs';
        }

        return '/settings/profile';
    }

    /**
     * @return list<array{title: string, subtitle: string, href: string, keywords?: list<string>}>
     */
    private function settingsPages(User $user): array
    {
        $pages = [
            [
                'title' => __('Profile'),
                'subtitle' => __('Settings'),
                'href' => '/settings/profile',
                'keywords' => ['account', 'me'],
            ],
            [
                'title' => __('Appearance'),
                'subtitle' => __('Settings'),
                'href' => '/settings/appearance',
                'keywords' => ['theme', 'colors'],
            ],
        ];

        $gated = [
            ['settings.manage', __('System Control'), '/settings/system-control', []],
            ['settings.manage', __('Offline & PWA'), '/settings/offline', []],
            ['settings.manage', __('Google Auth'), '/settings/google-auth', []],
            ['settings.manage', __('Translations'), '/settings/translations', ['translation', 'locale', 'language']],
            ['settings.manage', __('Translations'), '/settings/translations', ['i18n', 'locale']],
            ['users.view', __('Users'), '/settings/users', ['accounts', 'people']],
            ['roles.view', __('Roles'), '/settings/roles', ['permissions']],
            ['storage.view', __('File Manager'), '/settings/file-manager', ['files']],
            ['trash.view', __('Trash'), '/settings/trash', []],
            ['audit.view', __('Login logs'), '/settings/login-logs', ['auth']],
            ['audit.view', __('Action logs'), '/settings/action-logs', ['activity', 'audit', 'changes']],
            ['audit.view', __('System logs'), '/settings/system-logs', []],
        ];

        foreach ($gated as [$permission, $title, $href, $keywords]) {
            if (! $user->can($permission)) {
                continue;
            }

            $pages[] = [
                'title' => $title,
                'subtitle' => __('Settings'),
                'href' => $href,
                'keywords' => $keywords,
            ];
        }

        if (
            $user->can('backups.view')
            || $user->can('backups.run')
            || $user->can('backups.download')
        ) {
            $pages[] = [
                'title' => __('Backups'),
                'subtitle' => __('Settings'),
                'href' => '/settings/backups',
                'keywords' => ['backup', 'restore'],
            ];
        }

        if ($user->can('settings.manage') || $user->can('audit.view')) {
            $pages[] = [
                'title' => __('AI'),
                'subtitle' => __('Settings'),
                'href' => $user->can('settings.manage') ? '/settings/ai' : '/settings/ai/logs',
                'keywords' => ['ai', 'assistant'],
            ];
            $pages[] = [
                'title' => __('Email'),
                'subtitle' => __('Settings'),
                'href' => $user->can('settings.manage') ? '/settings/email' : '/settings/email/logs',
                'keywords' => ['mail', 'smtp'],
            ];
        }

        return $pages;
    }

    /**
     * @return Collection<int, array{id: string, title: string, subtitle: ?string, href: string, type: string}>
     */
    private function searchUsers(string $query): Collection
    {
        return User::query()
            ->tap(fn ($q) => EfficientQuery::applySearch(
                $q,
                $query,
                prefixColumns: ['name', 'email'],
                containsColumns: ['name'],
                exactColumns: ['email'],
            ))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $user): array => $this->item(
                id: 'user:'.$user->id,
                title: $user->name,
                subtitle: $user->email.($user->is_active ? '' : ' · '.__('Disabled')),
                href: '/settings/users?search='.urlencode($user->email),
                type: 'user',
            ));
    }

    /**
     * @return Collection<int, array{id: string, title: string, subtitle: ?string, href: string, type: string}>
     */
    private function searchRoles(string $query): Collection
    {
        $escaped = addcslashes($query, '%_\\');

        return Role::query()
            ->where('name', 'like', "%{$escaped}%")
            ->withCount('users')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Role $role): array => $this->item(
                id: 'role:'.$role->id,
                title: $role->name,
                subtitle: __(':count users', ['count' => $role->users_count ?? 0]),
                href: '/settings/roles',
                type: 'role',
            ));
    }

    /**
     * @return Collection<int, array{id: string, title: string, subtitle: ?string, href: string, type: string}>
     */
    private function searchFieldNotes(User $user, string $query): Collection
    {
        return FieldNote::query()
            ->where('user_id', $user->id)
            ->tap(fn ($q) => EfficientQuery::applySearch(
                $q,
                $query,
                prefixColumns: ['name'],
                containsColumns: ['name'],
            ))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get(['id', 'name'])
            ->map(fn (FieldNote $note): array => $this->item(
                id: 'field-note:'.$note->id,
                title: $note->name,
                subtitle: __('Field note'),
                href: route('field-notes.edit', $note, absolute: false),
                type: 'field_note',
            ));
    }

    /**
     * @return Collection<int, array{id: string, title: string, subtitle: ?string, href: string, type: string}>
     */
    private function searchBackups(string $query): Collection
    {
        return BackupRecord::query()
            ->tap(fn ($q) => EfficientQuery::applySearch(
                $q,
                $query,
                prefixColumns: ['name', 'uuid', 'type', 'status'],
                containsColumns: ['name', 'uuid'],
                exactColumns: ['uuid'],
            ))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'type', 'status', 'uuid'])
            ->map(fn (BackupRecord $backup): array => $this->item(
                id: 'backup:'.$backup->id,
                title: $backup->name,
                subtitle: trim(implode(' · ', array_filter([
                    $backup->type,
                    $backup->status,
                ]))),
                href: '/settings/backups?search='.urlencode($backup->name),
                type: 'backup',
            ));
    }

    /**
     * @return array{id: string, title: string, subtitle: ?string, href: string, type: string}
     */
    private function item(
        string $id,
        string $title,
        ?string $subtitle,
        string $href,
        string $type,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'href' => $href,
            'type' => $type,
        ];
    }
}
