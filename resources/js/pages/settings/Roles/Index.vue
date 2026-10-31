<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import Can from '@/components/Can.vue';
import { MisDataTable, MisEmptyState } from '@/components/mis';
import type { MisBulkConfirmRequest } from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import AccessSettingsTabs from '@/components/settings/AccessSettingsTabs.vue';
import PermissionMatrix from '@/components/settings/PermissionMatrix.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import type { RowActionItem } from '@/lib/row-actions';

type Permission = { id: number; name: string };

type RoleRecord = {
    id: number;
    name: string;
    users_count?: number;
    permissions?: Permission[];
};

const props = defineProps<{
    roles: RoleRecord[];
    permissions: Permission[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();
const protectedRoles = ['Owner'];
const editingRole = ref<RoleRecord | null>(null);
const editName = ref('');
const editPermissions = ref<string[]>([]);
const saving = ref(false);
const search = ref('');
const selected = ref<Array<string | number>>([]);
const bulkProcessing = ref(false);

const columns = computed(() => [
    { key: 'name', label: t('Role'), sortable: true },
    {
        key: 'permissions_count',
        label: t('Permissions'),
        sortable: true,
        align: 'end' as const,
        mono: true,
        width: '8rem',
    },
    {
        key: 'users_count',
        label: t('Users'),
        sortable: true,
        align: 'end' as const,
        mono: true,
        width: '7rem',
    },
    {
        key: 'actions',
        label: t('Actions'),
        align: 'end' as const,
        sticky: 'end' as const,
        width: '4.5rem',
    },
]);

const tableRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.roles
        .filter((role) => {
            if (!query) {
                return true;
            }

            return role.name.toLowerCase().includes(query);
        })
        .map((role) => ({
            ...role,
            permissions_count: role.permissions?.length ?? 0,
            users_count: role.users_count ?? 0,
            actions: role.id,
        }));
});

function roleFromRow(row: Record<string, unknown>): RoleRecord | null {
    const id = Number(row.id);
    return props.roles.find((role) => role.id === id) ?? null;
}

function askBulkDelete(
    ids: Array<string | number>,
    clear: () => void,
    confirm: (request: MisBulkConfirmRequest) => void,
): void {
    const filtered = ids
        .map((id) => Number(id))
        .filter((id) => {
            const role = props.roles.find((item) => item.id === id);
            return (
                role &&
                !protectedRoles.includes(role.name) &&
                !(role.users_count && role.users_count > 0)
            );
        });

    if (!filtered.length) {
        return;
    }

    confirm({
        title: t('Move to trash'),
        description: t('Move :count selected roles to trash?', {
            count: String(filtered.length),
        }),
        confirmLabel: t('Move to trash'),
        confirmVariant: 'destructive',
        onConfirm: () => {
            if (bulkProcessing.value) {
                return;
            }

            bulkProcessing.value = true;
            router.delete('/settings/roles/bulk', {
                data: { ids: filtered },
                preserveScroll: true,
                onSuccess: () => clear(),
                onFinish: () => {
                    bulkProcessing.value = false;
                },
            });
        },
    });
}

function openEdit(role: RoleRecord): void {
    editingRole.value = role;
    editName.value = role.name;
    editPermissions.value = role.permissions?.map((p) => p.name) ?? [];
}

function saveRole(): void {
    if (!editingRole.value || saving.value) {
        return;
    }

    saving.value = true;

    router.patch(
        `/settings/roles/${editingRole.value.id}`,
        {
            name: editName.value,
            permissions: editPermissions.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingRole.value = null;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function roleActions(role: RoleRecord): RowActionItem[] {
    return [
        {
            label: t('Edit'),
            icon: Pencil,
            onClick: () => openEdit(role),
            hidden: !can('roles.edit'),
        },
        {
            label: t('Move to trash'),
            icon: Trash2,
            variant: 'destructive',
            separator: true,
            href: `/settings/roles/${role.id}`,
            method: 'delete',
            hidden: protectedRoles.includes(role.name) || !can('roles.delete'),
            confirm: {
                title: t('Move to trash'),
                description: t(
                    'This role will move to Storage → Trash and can be restored later.',
                ),
                confirmLabel: t('Move to trash'),
            },
        },
    ];
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/roles' },
            { title: 'Roles', href: '/settings/roles' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Role Management')" />

    <SettingsPage>
        <template #tabs>
            <AccessSettingsTabs />
        </template>

        <MisEmptyState v-if="roles.length === 0">
            <template #default>
                {{ t('No roles found') }}
            </template>
            <template #actions>
                <Can permission="roles.create">
                    <Button
                        as-child
                        size="icon"
                        class="size-8 rounded-lg"
                    >
                        <Link
                            href="/settings/roles/create"
                            :title="t('Create role')"
                            :aria-label="t('Create role')"
                        >
                            <Plus class="size-4" />
                        </Link>
                    </Button>
                </Can>
            </template>
        </MisEmptyState>

        <MisDataTable
            v-else
            v-model:selected="selected"
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="id"
            toolbar
            searchable
            selectable
            selection-toggle
            column-toggle
            export-filename="roles"
            :search-placeholder="t('Search roles…')"
            :empty-label="t('No roles found')"
        >
            <template #actions>
                <Can permission="roles.create">
                    <Button
                        as-child
                        size="icon"
                        class="size-8 rounded-lg"
                    >
                        <Link
                            href="/settings/roles/create"
                            :title="t('Create role')"
                            :aria-label="t('Create role')"
                        >
                            <Plus class="size-4" />
                        </Link>
                    </Button>
                </Can>
            </template>

            <template #bulk="{ selectedKeys, clear, confirm }">
                <Can permission="roles.delete">
                    <Button
                        size="sm"
                        variant="destructive"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="askBulkDelete(selectedKeys, clear, confirm)"
                    >
                        <Trash2 class="size-3.5" />
                        {{ t('Move to trash') }}
                    </Button>
                </Can>
            </template>

            <template #cell-name="{ row }">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium tracking-tight">
                            {{ roleFromRow(row)?.name }}
                        </p>
                        <span
                            v-if="
                                protectedRoles.includes(
                                    roleFromRow(row)?.name ?? '',
                                )
                            "
                            class="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            {{ t('Protected') }}
                        </span>
                    </div>
                </div>
            </template>

            <template #cell-permissions_count="{ value }">
                <span class="tabular-nums text-muted-foreground">{{
                    value
                }}</span>
            </template>

            <template #cell-users_count="{ value }">
                <span
                    class="inline-flex items-center justify-end gap-1 tabular-nums text-muted-foreground"
                >
                    <Users class="size-3.5 opacity-70" />
                    {{ value }}
                </span>
            </template>

            <template #cell-actions="{ row }">
                <div
                    v-if="roleFromRow(row) && can('roles.edit')"
                    class="flex justify-end"
                >
                    <RowActionsMenu :actions="roleActions(roleFromRow(row)!)" />
                </div>
            </template>
        </MisDataTable>

        <Dialog
            :open="!!editingRole"
            @update:open="(open) => !open && (editingRole = null)"
        >
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('Edit') }} {{ editingRole?.name }}
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="role-name">{{ t('Name') }}</Label>
                        <Input
                            id="role-name"
                            v-model="editName"
                            :disabled="
                                protectedRoles.includes(editingRole?.name ?? '')
                            "
                        />
                    </div>

                    <PermissionMatrix
                        v-model="editPermissions"
                        :permissions="permissions"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        variant="ghost"
                        :disabled="saving"
                        @click="editingRole = null"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button :disabled="saving" @click="saveRole">
                        {{ t('Save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SettingsPage>
</template>
