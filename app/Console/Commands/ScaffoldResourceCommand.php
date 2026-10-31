<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScaffoldResourceCommand extends Command
{
    protected $signature = 'kit:scaffold
        {name : Resource name in StudlyCase (e.g. Product)}
        {--offline : Generate client_uuid, sync handler, offline config, and offline-aware Vue forms}
        {--user-scoped : Scope offline records to the authenticated user (recommended for field data)}
        {--attachments : Register the resource for offline attachment uploads (requires HasAttachments)}
        {--force : Overwrite existing model, controller, handler, and Vue stubs}';

    protected $description = 'Scaffold a soft-delete aware model, migration, controller, and Vue CRUD stubs';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $table = Str::snake(Str::pluralStudly($name));
        $route = Str::kebab(Str::pluralStudly($name));
        // Permission / sync keys must be snake_plural (field_notes), not kebab (field-notes).
        $permission = $table;
        $resourceKey = $table;
        $offline = (bool) $this->option('offline');
        $userScoped = (bool) $this->option('user-scoped') || $offline;
        $attachments = (bool) $this->option('attachments');
        $force = (bool) $this->option('force');

        $modelPath = app_path("Models/{$name}.php");
        $controllerPath = app_path("Http/Controllers/Admin/{$name}Controller.php");
        $pagesDir = resource_path("js/pages/resources/{$route}");
        $handlerPath = app_path("Sync/Handlers/{$name}SyncHandler.php");

        if (File::exists($modelPath) && ! $force) {
            $this->error("Model already exists: {$modelPath}");
            $this->line('  Re-run with --force to overwrite stubs (migration will not be re-created).');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(app_path('Http/Controllers/Admin'));
        File::ensureDirectoryExists($pagesDir);

        File::put($modelPath, $this->modelStub($name, $offline, $userScoped, $attachments));
        File::put($controllerPath, $this->controllerStub($name, $route, $permission, $offline, $resourceKey, $userScoped));
        File::put("{$pagesDir}/Index.vue", $this->indexStub($name, $route, $offline, $resourceKey));
        File::put("{$pagesDir}/Create.vue", $this->formStub($name, $route, 'Create', $offline, $resourceKey));
        File::put("{$pagesDir}/Edit.vue", $this->formStub($name, $route, 'Edit', $offline, $resourceKey));

        if ($offline) {
            File::ensureDirectoryExists(app_path('Sync/Handlers'));
            File::put($handlerPath, $this->handlerStub($name, $userScoped));
        }

        $migrationExists = File::glob(database_path("migrations/*_create_{$table}_table.php"));

        if ($migrationExists === [] || $migrationExists === false) {
            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--create' => $table,
            ]);

            $this->patchLatestMigration($table, $offline, $userScoped);
        } elseif ($force) {
            $this->line("  Migration:  skipped (already exists for {$table})");
        } else {
            $this->patchLatestMigration($table, $offline, $userScoped);
        }

        $this->newLine();
        $this->info("Scaffolded {$name}".($offline ? ' (offline)' : '').'.');
        $this->line("  Model:      app/Models/{$name}.php (SoftDeletes".($offline ? ' + client_uuid' : '').')');
        $this->line("  Controller: app/Http/Controllers/Admin/{$name}Controller.php");
        $this->line("  Pages:      resources/js/pages/resources/{$route}/");

        if ($offline) {
            $this->line("  Handler:    app/Sync/Handlers/{$name}SyncHandler.php");
        }

        $this->registerAiResource($name, $table, $route, $permission, $offline);
        $this->registerTrashResource($name, $table);
        $this->registerMisPermission($permission);

        if ($offline) {
            $this->registerOfflineResource($name, $resourceKey, $attachments, $userScoped);
        }

        $this->line('  Next:');
        $this->line("    1. Register routes for /{$route} (e.g. Route::resource('{$route}', {$name}Controller::class)->parameters(['{$route}' => '".Str::camel($name)."'])->except(['show']);)");
        $this->line('    2. Run php artisan migrate');
        $this->line('    3. Run php artisan mis:sync-permissions');

        if ($offline) {
            $this->line('    Offline: handler + warm cache + client pull registered automatically.');
            $this->line('    Forms use OfflineResourceForm / useOfflineResourceList — no extra wiring needed.');
        }

        return self::SUCCESS;
    }

    protected function patchLatestMigration(string $table, bool $offline, bool $userScoped): void
    {
        $files = File::glob(database_path('migrations/*_create_'.$table.'_table.php'));

        if ($files === [] || $files === false) {
            $this->warn('Could not locate generated migration to patch offline columns.');

            return;
        }

        sort($files);
        $path = end($files);
        $contents = File::get($path);

        if (! str_contains($contents, "Schema::create('{$table}'")) {
            return;
        }

        $extra = "\$table->id();\n";

        if ($offline) {
            $extra .= "            \$table->uuid('client_uuid')->unique();\n";
        }

        if ($userScoped) {
            $extra .= "            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n";
        }

        $extra .= "            \$table->string('name');\n";

        if ($offline) {
            $extra .= "            \$table->timestamp('client_updated_at')->nullable();\n";
        }

        $extra .= "            \$table->timestamps();\n            \$table->softDeletes();";

        $updated = preg_replace(
            '/\$table->id\(\);\s*\$table->timestamps\(\);/s',
            $extra,
            $contents,
            1,
        );

        if (! is_string($updated) || $updated === $contents) {
            $this->warn('Could not auto-patch migration columns — add client_uuid / softDeletes manually.');

            return;
        }

        File::put($path, $updated);
        $this->line('  Migration:  offline columns patched automatically');
    }

    protected function registerMisPermission(string $permission): void
    {
        $configPath = config_path('mis_permissions.php');

        if (! File::exists($configPath)) {
            return;
        }

        $contents = File::get($configPath);

        if (str_contains($contents, "'{$permission}' =>")) {
            $this->line("  Permissions: module [{$permission}] already present");

            return;
        }

        $entry = <<<PHP

        '{$permission}' => [
            'extra' => [],
        ],

PHP;

        $updated = preg_replace(
            '/(\n\s*\'ai\'\s*=>\s*\[[^\]]*\]\s*,)/s',
            $entry.'$1',
            $contents,
            1,
        );

        if (! is_string($updated) || $updated === $contents) {
            $updated = preg_replace('/\n    \];\s*\n\];\s*$/', "\n".$entry."    ];\n];\n", $contents, 1);
        }

        if (! is_string($updated) || $updated === $contents) {
            $this->warn('Could not append mis_permissions module automatically.');

            return;
        }

        File::put($configPath, $updated);
        $this->line("  Permissions: module [{$permission}] registered");
    }

    protected function registerOfflineResource(
        string $name,
        string $resourceKey,
        bool $attachments,
        bool $userScoped,
    ): void {
        $configPath = config_path('offline.php');

        if (! File::exists($configPath)) {
            $this->warn('config/offline.php missing — skipped offline registration.');

            return;
        }

        $contents = File::get($configPath);

        if (! str_contains($contents, "'{$resourceKey}' => \\App\\Sync\\Handlers\\{$name}SyncHandler::class")) {
            $handlerLine = "        '{$resourceKey}' => \\App\\Sync\\Handlers\\{$name}SyncHandler::class,\n";
            // Match top-level resources map only (4-space indent), not warm.resources (more indent).
            $updated = preg_replace(
                "/(\n    'resources'\\s*=>\\s*\\[)\n/",
                "$1\n".$handlerLine,
                $contents,
                1,
            );

            if (is_string($updated) && $updated !== $contents) {
                $contents = $updated;
                $this->line("  Offline:    resources.{$resourceKey} registered");
            } else {
                $this->warn("Could not register offline.resources.{$resourceKey} automatically.");
            }
        }

        if (! str_contains($contents, "'{$resourceKey}' => ['ttl'")) {
            $warmLine = "            '{$resourceKey}' => ['ttl' => 3600],\n";
            $updated = preg_replace(
                "/('warm'\\s*=>\\s*\\[[\\s\\S]*?'resources'\\s*=>\\s*\\[)\n/",
                '$1'."\n".$warmLine,
                $contents,
                1,
            );

            if (is_string($updated) && $updated !== $contents) {
                $contents = $updated;
                $this->line("  Offline:    warm.resources.{$resourceKey} registered");
            }
        }

        if ($attachments && ! str_contains($contents, "'{$resourceKey}' => [\n            'model' => \\App\\Models\\{$name}::class")) {
            $userScopedPhp = $userScoped ? 'true' : 'false';
            $parentEntry = <<<PHP
        '{$resourceKey}' => [
            'model' => \\App\\Models\\{$name}::class,
            'user_scoped' => {$userScopedPhp},
            'defaults' => [
                'name' => 'Offline draft',
            ],
        ],

PHP;
            $updated = preg_replace(
                "/('attachment_parents'\\s*=>\\s*\\[)\\s*/",
                "$1\n".$parentEntry,
                $contents,
                1,
            );

            if (is_string($updated) && $updated !== $contents) {
                $contents = $updated;
                $this->line("  Offline:    attachment_parents.{$resourceKey} registered");
            }
        }

        File::put($configPath, $contents);
    }

    protected function registerAiResource(
        string $name,
        string $table,
        string $route,
        string $permission,
        bool $offline,
    ): void {
        unset($table);
        $configPath = config_path('ai_resources.php');

        if (! File::exists($configPath)) {
            $this->warn('config/ai_resources.php missing — skipped AI resource registration.');

            return;
        }

        $contents = File::get($configPath);

        if (str_contains($contents, "'{$permission}' =>")) {
            $this->line("  AI:         resource [{$permission}] already present in ai_resources.php");

            return;
        }

        $modelFqcn = "App\\Models\\{$name}";
        $fillable = "['name']";
        $search = "['name']";
        $createExtras = $offline
            ? "['client_uuid', 'client_updated_at']"
            : '[]';

        $entry = <<<PHP

    '{$permission}' => [
        'label' => '{$name}',
        'model' => \\{$modelFqcn}::class,
        'permission' => '{$permission}',
        'index_route' => '{$route}.index',
        'edit_route' => '{$route}.edit',
        'user_scoped' => {$this->bool($offline)},
        'search' => {$search},
        'default_order' => ['id', 'desc'],
        'display' => ['id', 'name'],
        'title_attribute' => 'name',
        'fillable' => {$fillable},
        'create_extras' => {$createExtras},
        'rules' => [
            'create' => [
                'name' => ['required', 'string', 'max:255'],
            ],
            'update' => [
                'name' => ['sometimes', 'string', 'max:255'],
            ],
        ],
        'sensitive' => [],
        'require_approval' => ['delete'],
        'privilege_fields' => [],
        'protected_names' => [],
    ],

PHP;

        $updated = preg_replace('/\n\];\s*$/', "\n".$entry."];\n", $contents, 1);

        if (! is_string($updated) || $updated === $contents) {
            $this->warn('Could not append AI resource entry automatically — add it to config/ai_resources.php manually.');

            return;
        }

        File::put($configPath, $updated);
        $this->line('  AI:         config/ai_resources.php entry registered');
    }

    protected function registerTrashResource(string $name, string $table): void
    {
        $configPath = config_path('trash.php');

        if (! File::exists($configPath)) {
            $this->warn('config/trash.php missing — skipped trash registration.');

            return;
        }

        $contents = File::get($configPath);

        if (str_contains($contents, "'{$table}' => [")) {
            return;
        }

        $label = Str::headline(Str::singular($table));
        $entry = <<<PHP

        '{$table}' => [
            'model' => App\\Models\\{$name}::class,
            'label' => '{$label}',
            'title' => 'name',
        ],

PHP;

        $updated = preg_replace('/\n    \],\s*\n\];\s*$/', "\n".$entry."    ],\n];\n", $contents, 1);

        if (! is_string($updated) || $updated === $contents) {
            $this->warn('Could not append trash registry entry automatically — add it to config/trash.php manually.');

            return;
        }

        File::put($configPath, $updated);
        $this->line('  Trash:      config/trash.php entry registered');
    }

    protected function modelStub(string $name, bool $offline, bool $userScoped, bool $attachments): string
    {
        $uses = [
            'use App\\Concerns\\Trashable;',
            'use Illuminate\\Database\\Eloquent\\Model;',
            'use Illuminate\\Database\\Eloquent\\SoftDeletes;',
        ];
        $traits = ['SoftDeletes', 'Trashable'];

        if ($offline) {
            $uses[] = 'use App\\Concerns\\HasOfflineSync;';
            $traits[] = 'HasOfflineSync';
        }

        if ($attachments) {
            $uses[] = 'use App\\Concerns\\HasAttachments;';
            $traits[] = 'HasAttachments';
        }

        if ($userScoped) {
            $uses[] = 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;';
        }

        $fillable = ["'name'"];

        if ($offline) {
            array_unshift($fillable, "'client_uuid'");
            $fillable[] = "'client_updated_at'";
        }

        if ($userScoped) {
            $fillable[] = "'user_id'";
        }

        $fillablePhp = implode(",\n        ", $fillable);

        $casts = $offline
            ? <<<'PHP'

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_updated_at' => 'datetime',
        ];
    }

PHP
            : '';

        $relations = $userScoped
            ? <<<'PHP'

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

PHP
            : '';

        $useBlock = implode("\n", array_unique($uses));
        $traitBlock = implode(', ', $traits);

        return <<<PHP
<?php

namespace App\Models;

{$useBlock}

class {$name} extends Model
{
    use {$traitBlock};

    protected \$fillable = [
        {$fillablePhp},
    ];
{$casts}{$relations}}

PHP;
    }

    protected function controllerStub(
        string $name,
        string $route,
        string $permission,
        bool $offline,
        string $resourceKey,
        bool $userScoped = false,
    ): string {
        $uuidImport = $offline ? "use Illuminate\\Support\\Str;\n" : '';
        $offlineImport = $offline
            ? "use App\\Http\\Controllers\\Concerns\\SharesOfflineInertiaProps;\n"
            : '';
        $offlineTrait = $offline ? "    use SharesOfflineInertiaProps;\n" : '';
        $offlineProps = $offline
            ? "...\$this->offlineInertiaProps('{$resourceKey}'),"
            : "'offlineEnabled' => false,";

        $var = Str::camel($name);
        $query = $userScoped
            ? "{$name}::query()\n            ->where('user_id', \$request->user()->id)\n            ->latest()\n            ->paginate(20)\n            ->withQueryString();"
            : "{$name}::query()->latest()->paginate(20)->withQueryString();";

        $ownership = $userScoped
            ? "\n        abort_unless(\${$var}->user_id === \$request->user()->id, 404);\n"
            : '';

        $createPayload = $offline
            ? "'client_uuid' => \$validated['client_uuid'] ?? (string) Str::uuid(),\n"
                .($userScoped ? "            'user_id' => \$request->user()->id,\n" : '')
                ."            'name' => \$validated['name'],\n            'client_updated_at' => now(),"
            : ($userScoped
                ? "'user_id' => \$request->user()->id,\n            ...\$validated,"
                : '...$validated');

        $storeRules = $offline
            ? "'client_uuid' => ['nullable', 'uuid'],\n            'name' => ['required', 'string', 'max:255'],"
            : "'name' => ['required', 'string', 'max:255'],";

        return <<<PHP
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
{$offlineImport}use App\Http\Controllers\Controller;
use App\Models\\{$name};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
{$uuidImport}use Inertia\Inertia;
use Inertia\Response;

class {$name}Controller extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;
{$offlineTrait}
    public function index(Request \$request): Response
    {
        \$this->authorizePermission(\$request, '{$permission}.view');

        \$items = {$query}

        return Inertia::render('resources/{$route}/Index', [
            'items' => \$this->inertiaPagination(\$items),
            {$offlineProps}
        ]);
    }

    public function create(Request \$request): Response
    {
        \$this->authorizePermission(\$request, '{$permission}.create');

        return Inertia::render('resources/{$route}/Create', [
            {$offlineProps}
        ]);
    }

    public function store(Request \$request): RedirectResponse
    {
        \$this->authorizePermission(\$request, '{$permission}.create');

        \$validated = \$request->validate([
            {$storeRules}
        ]);

        {$name}::query()->create([
            {$createPayload}
        ]);

        return redirect()->route('{$route}.index')->with('success', __('Created.'));
    }

    public function edit(Request \$request, {$name} \${$var}): Response
    {
        \$this->authorizePermission(\$request, '{$permission}.edit');{$ownership}
        return Inertia::render('resources/{$route}/Edit', [
            'item' => \${$var},
            {$offlineProps}
        ]);
    }

    public function update(Request \$request, {$name} \${$var}): RedirectResponse
    {
        \$this->authorizePermission(\$request, '{$permission}.edit');{$ownership}
        \$validated = \$request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        \${$var}->update([
            ...\$validated,
            {$this->offlineUpdatedAt($offline)}
        ]);

        return back()->with('success', __('Updated.'));
    }

    public function destroy(Request \$request, {$name} \${$var}): RedirectResponse
    {
        \$this->authorizePermission(\$request, '{$permission}.delete');{$ownership}
        \${$var}->delete();

        return back()->with('success', __('Deleted.'));
    }
}

PHP;
    }

    protected function offlineUpdatedAt(bool $offline): string
    {
        return $offline ? "'client_updated_at' => now()," : '';
    }

    protected function handlerStub(string $name, bool $userScoped): string
    {
        $userScopedPhp = $userScoped ? 'true' : 'false';

        return <<<PHP
<?php

namespace App\Sync\Handlers;

use App\Models\\{$name};
use App\Sync\AbstractOfflineSyncHandler;

class {$name}SyncHandler extends AbstractOfflineSyncHandler
{
    protected function modelClass(): string
    {
        return {$name}::class;
    }

    protected function userScoped(): bool
    {
        return {$userScopedPhp};
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function attributesFromValidated(array \$data): array
    {
        return [
            'name' => \$data['name'],
        ];
    }
}

PHP;
    }

    protected function indexStub(string $name, string $route, bool $offline, string $resourceKey): string
    {
        if (! $offline) {
            return $this->plainIndexStub($name, $route);
        }

        return <<<VUE
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import MisPagination from '@/components/MisPagination.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';
import { useOfflineResourceList } from '@/offline/useOfflineResourceList';

const props = defineProps<{
    items: Paginated<{ id: number; name: string; client_uuid?: string }>;
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();
const onlineItems = computed(() => props.items.data);

const { displayItems, showingOfflineCache, online } = useOfflineResourceList({
    resource: '{$resourceKey}',
    offlineEnabled: toRef(props, 'offlineEnabled'),
    onlineItems,
});

defineOptions(resourcePageBreadcrumbs('{$name}', '/{$route}'));
</script>

<template>
    <Head :title="t('{$name}')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('{$name}') }}</h1>
            <Link
                :href="'/' + '{$route}/create'"
                class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            >
                {{ t('Create') }}
            </Link>
        </div>

        <p
            v-if="showingOfflineCache"
            class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm"
        >
            {{ t('Showing cached and queued records from this device.') }}
        </p>

        <div class="rounded-md border">
            <ul class="divide-y">
                <li
                    v-for="item in displayItems"
                    :key="item.client_uuid || item.id"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span>{{ item.name || item.title }}</span>
                        <Badge v-if="item.pending_local || item.offline_only" variant="outline">
                            {{ t('Pending sync') }}
                        </Badge>
                    </div>
                    <Link
                        v-if="item.id"
                        :href="'/' + '{$route}/' + item.id + '/edit'"
                        class="text-sm text-primary"
                    >
                        {{ t('Edit') }}
                    </Link>
                </li>
            </ul>
            <div v-if="online" class="px-4 pb-4">
                <MisPagination :pagination="items" />
            </div>
        </div>
    </div>
</template>

VUE;
    }

    protected function plainIndexStub(string $name, string $route): string
    {
        return <<<VUE
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MisPagination from '@/components/MisPagination.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

defineProps<{
    items: Paginated<{ id: number; name: string }>;
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();

defineOptions(resourcePageBreadcrumbs('{$name}', '/{$route}'));
</script>

<template>
    <Head :title="t('{$name}')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('{$name}') }}</h1>
            <Link
                :href="'/' + '{$route}/create'"
                class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            >
                {{ t('Create') }}
            </Link>
        </div>

        <div class="rounded-md border">
            <ul class="divide-y">
                <li
                    v-for="item in items.data"
                    :key="item.id"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span>{{ item.name }}</span>
                    <Link :href="'/' + '{$route}/' + item.id + '/edit'" class="text-sm text-primary">
                        {{ t('Edit') }}
                    </Link>
                </li>
            </ul>
            <div class="px-4 pb-4">
                <MisPagination :pagination="items" />
            </div>
        </div>
    </div>
</template>

VUE;
    }

    protected function formStub(string $name, string $route, string $mode, bool $offline, string $resourceKey): string
    {
        if ($offline) {
            return $this->offlineFormStub($name, $route, $mode, $resourceKey);
        }

        $isEdit = $mode === 'Edit';
        $pageKey = $isEdit ? 'edit' : 'create';

        return <<<VUE
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MisForm, MisInputField, MisPage } from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const props = defineProps<{
    item?: { id: number; name: string };
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.item?.name ?? '',
});

function submit() {
    if ({$this->bool($isEdit)}) {
        form.put('/{$route}/' + props.item!.id);
    } else {
        form.post('/{$route}');
    }
}

defineOptions(resourcePageBreadcrumbs('{$name}', '/{$route}', '{$pageKey}'));
</script>

<template>
    <Head :title="t('{$mode} {$name}')" />

    <MisPage narrow>
        <MisForm
            cancel-href="/{$route}"
            :processing="form.processing"
            max-width="full"
            @submit="submit"
        >
            <MisInputField
                id="name"
                v-model="form.name"
                :label="t('Name')"
                :error="form.errors.name"
                required
            />
        </MisForm>
    </MisPage>
</template>

VUE;
    }

    protected function offlineFormStub(string $name, string $route, string $mode, string $resourceKey): string
    {
        $isEdit = $mode === 'Edit';
        $pageKey = $isEdit ? 'edit' : 'create';
        $onlineCall = $isEdit
            ? "form.put('/{$route}/' + props.item!.id);"
            : "form.post('/{$route}');";
        $resetLocal = $isEdit ? 'false' : 'true';

        return <<<VUE
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import OfflineResourceForm from '@/components/OfflineResourceForm.vue';
import { MisInputField, MisPage } from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';
import { useOfflineResourceForm } from '@/offline/useOfflineResourceForm';

const props = defineProps<{
    item?: { id: number; name: string; client_uuid?: string };
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();

const form = useForm({
    client_uuid: props.item?.client_uuid ?? crypto.randomUUID(),
    name: props.item?.name ?? '',
});

const {
    local,
    clientUuid,
    offlineEnabled,
    online,
    processing,
    attachmentsEnabled,
    onSubmit,
    onAttachmentStored,
} = useOfflineResourceForm({
    resource: '{$resourceKey}',
    offlineEnabled: Boolean(props.offlineEnabled),
    initialClientUuid: form.client_uuid,
    initialValues: {
        name: props.item?.name ?? '',
    },
    resetLocalOnQueue: {$resetLocal},
    onlineSubmit: async (payload) => {
        form.client_uuid = String(payload.client_uuid);
        form.name = String(payload.name ?? '');
        {$onlineCall}
    },
});

defineOptions(resourcePageBreadcrumbs('{$name}', '/{$route}', '{$pageKey}'));
</script>

<template>
    <Head :title="t('{$mode} {$name}')" />

    <MisPage narrow>
        <OfflineResourceForm
            :offline-enabled="offlineEnabled"
            :online="online"
            :processing="processing || form.processing"
            :client-uuid="clientUuid"
            resource="{$resourceKey}"
            :show-attachments="attachmentsEnabled"
            cancel-href="/{$route}"
            max-width="full"
            @submit="onSubmit"
            @attachment-stored="onAttachmentStored"
        >
            <MisInputField
                id="name"
                v-model="local.name"
                :label="t('Name')"
                :error="form.errors.name"
                required
            />
        </OfflineResourceForm>
    </MisPage>
</template>

VUE;
    }

    protected function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
