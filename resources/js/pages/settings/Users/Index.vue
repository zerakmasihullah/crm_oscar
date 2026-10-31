<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    KeyRound,
    Pencil,
    Plus,
    Shield,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import InputError from '@/components/InputError.vue';
import { MisDataTable, MisPagination } from '@/components/mis';
import type { MisBulkConfirmRequest } from '@/components/mis';
import PasswordInput from '@/components/PasswordInput.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import AccessSettingsTabs from '@/components/settings/AccessSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type Role = { id: number; name: string };

type UserRecord = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles?: Role[];
};

type EditMode = 'profile' | 'password';

const props = defineProps<{
    users: Paginated<UserRecord>;
    roles: Role[];
    filters?: { search?: string | null };
}>();

const { t } = useTranslations();
const page = usePage();
const currentUserId = computed(
    () => page.props.auth?.user?.id as number | undefined,
);

const search = ref(props.filters?.search ?? '');
const selected = ref<Array<string | number>>([]);
const bulkProcessing = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const editingUser = ref<UserRecord | null>(null);
const editMode = ref<EditMode>('profile');

const editForm = useForm({
    name: '',
    email: '',
    roles: [] as string[],
    password: '',
    password_confirmation: '',
});

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters?.search ?? '').trim();
        if (next === current) {
            return;
        }

        router.get(
            '/settings/users',
            { search: next || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

function openEdit(user: UserRecord, mode: EditMode = 'profile'): void {
    editingUser.value = user;
    editMode.value = mode;
    editForm.clearErrors();
    editForm.defaults({
        name: user.name,
        email: user.email,
        roles: user.roles?.map((role) => role.name) ?? [],
        password: '',
        password_confirmation: '',
    });
    editForm.reset();
}

function closeEdit(): void {
    editingUser.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function toggleEditRole(name: string, enable: boolean): void {
    if (enable) {
        if (!editForm.roles.includes(name)) {
            editForm.roles = [...editForm.roles, name];
        }
        return;
    }

    editForm.roles = editForm.roles.filter((role) => role !== name);
}

function saveEdit(): void {
    if (!editingUser.value) {
        return;
    }

    const userId = editingUser.value.id;

    if (editMode.value === 'password') {
        editForm
            .transform((data) => ({
                password: data.password,
                password_confirmation: data.password_confirmation,
            }))
            .patch(`/settings/users/${userId}`, {
                preserveScroll: true,
                onSuccess: () => closeEdit(),
                onFinish: () => {
                    editForm.transform((data) => data);
                },
            });
        return;
    }

    editForm
        .transform((data) => ({
            name: data.name,
            email: data.email,
            roles: data.roles,
        }))
        .patch(`/settings/users/${userId}`, {
            preserveScroll: true,
            onSuccess: () => closeEdit(),
            onFinish: () => {
                editForm.transform((data) => data);
            },
        });
}

function userActions(user: UserRecord): RowActionItem[] {
    const actions: RowActionItem[] = [
        {
            label: t('Edit'),
            icon: Pencil,
            onClick: () => openEdit(user, 'profile'),
        },
        {
            label: t('Change password'),
            icon: KeyRound,
            onClick: () => openEdit(user, 'password'),
        },
    ];

    if (user.id !== currentUserId.value) {
        actions.push({
            label: user.is_active ? t('Disable') : t('Enable'),
            icon: user.is_active ? Ban : CheckCircle2,
            separator: true,
            variant: user.is_active ? 'destructive' : 'default',
            method: 'patch',
            href: `/settings/users/${user.id}`,
            data: { is_active: !user.is_active },
            confirm: {
                title: user.is_active ? t('Disable user') : t('Enable user'),
                description: user.is_active
                    ? t('This user will no longer be able to sign in.')
                    : t('This user will be able to sign in again.'),
                confirmLabel: user.is_active ? t('Disable') : t('Enable'),
            },
            confirmVariant: user.is_active ? 'destructive' : 'default',
        });
    }

    return actions;
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (!parts.length) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
}

const columns = computed(() => [
    { key: 'user', label: t('User'), sortable: true },
    { key: 'email', label: t('Email'), sortable: true, truncate: true },
    { key: 'roles', label: t('Roles') },
    {
        key: 'status',
        label: t('Status'),
        sortable: true,
        width: '7.5rem',
    },
    {
        key: 'actions',
        label: t('Actions'),
        align: 'end' as const,
        sticky: 'end' as const,
        width: '4.5rem',
    },
]);

const tableRows = computed(() =>
    props.users.data.map((user) => ({
        ...user,
        user: user.name,
        email: user.email,
        status: user.is_active ? 'active' : 'disabled',
        actions: user.id,
    })),
);

function userFromRow(row: Record<string, unknown>): UserRecord | null {
    const id = Number(row.id);
    return props.users.data.find((user) => user.id === id) ?? null;
}

function askBulkStatus(
    ids: Array<string | number>,
    clear: () => void,
    confirm: (request: MisBulkConfirmRequest) => void,
    enable: boolean,
): void {
    const filtered = ids
        .map((id) => Number(id))
        .filter((id) => id !== currentUserId.value);

    if (!filtered.length) {
        return;
    }

    confirm({
        title: enable ? t('Enable') : t('Disable'),
        description: enable
            ? t('Enable :count selected users?', {
                  count: String(filtered.length),
              })
            : t('Disable :count selected users?', {
                  count: String(filtered.length),
              }),
        confirmLabel: enable ? t('Enable') : t('Disable'),
        confirmVariant: enable ? 'default' : 'destructive',
        onConfirm: () => {
            if (bulkProcessing.value) {
                return;
            }

            bulkProcessing.value = true;
            router.patch(
                '/settings/users/bulk-status',
                { ids: filtered, is_active: enable },
                {
                    preserveScroll: true,
                    onSuccess: () => clear(),
                    onFinish: () => {
                        bulkProcessing.value = false;
                    },
                },
            );
        },
    });
}

function askBulkDelete(
    ids: Array<string | number>,
    clear: () => void,
    confirm: (request: MisBulkConfirmRequest) => void,
): void {
    const filtered = ids
        .map((id) => Number(id))
        .filter((id) => id !== currentUserId.value);

    if (!filtered.length) {
        return;
    }

    confirm({
        title: t('Delete'),
        description: t('Delete :count selected users?', {
            count: String(filtered.length),
        }),
        confirmLabel: t('Delete'),
        confirmVariant: 'destructive',
        onConfirm: () => {
            if (bulkProcessing.value) {
                return;
            }

            bulkProcessing.value = true;
            router.delete('/settings/users/bulk', {
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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/users' },
            { title: 'Users', href: '/settings/users' },
        ],
    },
});
</script>

<template>
    <Head :title="t('User Management')" />

    <SettingsPage>
        <template #tabs>
            <AccessSettingsTabs />
        </template>

        <MisDataTable
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
            export-filename="users"
            export-href="/settings/users/export"
            print-href="/settings/users/print"
            :search-placeholder="t('Search by name or email…')"
            :empty-label="t('No users found')"
        >
            <template #actions>
                <Can permission="users.create">
                    <Button
                        as-child
                        size="icon"
                        class="size-8 rounded-lg"
                    >
                        <Link
                            href="/settings/users/create"
                            :title="t('Create user')"
                            :aria-label="t('Create user')"
                        >
                            <Plus class="size-4" />
                        </Link>
                    </Button>
                </Can>
            </template>

            <template #bulk="{ selectedKeys, clear, confirm }">
                <Can permission="users.edit">
                    <Button
                        size="sm"
                        variant="secondary"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="
                            askBulkStatus(selectedKeys, clear, confirm, true)
                        "
                    >
                        <CheckCircle2 class="size-3.5" />
                        {{ t('Enable') }}
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="
                            askBulkStatus(selectedKeys, clear, confirm, false)
                        "
                    >
                        <Ban class="size-3.5" />
                        {{ t('Disable') }}
                    </Button>
                </Can>
                <Can permission="users.delete">
                    <Button
                        size="sm"
                        variant="destructive"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="askBulkDelete(selectedKeys, clear, confirm)"
                    >
                        <Trash2 class="size-3.5" />
                        {{ t('Delete') }}
                    </Button>
                </Can>
            </template>

            <template #empty>
                <div class="space-y-1 py-2">
                    <p class="text-sm font-medium text-foreground/80">
                        {{ t('No users found') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Try a different search or create a new user.') }}
                    </p>
                </div>
            </template>

            <template #cell-user="{ row }">
                <div
                    v-if="userFromRow(row)"
                    class="flex min-w-0 items-center gap-3"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold tracking-tight text-muted-foreground"
                        aria-hidden="true"
                    >
                        {{ initials(userFromRow(row)!.name) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium tracking-tight">
                                {{ userFromRow(row)!.name }}
                            </p>
                            <span
                                v-if="userFromRow(row)!.id === currentUserId"
                                class="text-[10px] font-medium text-muted-foreground"
                            >
                                {{ t('You') }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-email="{ value }">
                <span class="text-muted-foreground">{{ value }}</span>
            </template>

            <template #cell-roles="{ row }">
                <div
                    v-if="userFromRow(row)?.roles?.length"
                    class="flex flex-wrap gap-1"
                >
                    <span
                        v-for="role in userFromRow(row)!.roles"
                        :key="role.id"
                        class="inline-flex items-center gap-1 rounded-md bg-muted/70 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                    >
                        <Shield class="size-2.5" />
                        {{ role.name }}
                    </span>
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </template>

            <template #cell-status="{ row }">
                <span
                    v-if="userFromRow(row) && !userFromRow(row)!.is_active"
                    class="inline-flex rounded-md bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive"
                >
                    {{ t('Disabled') }}
                </span>
                <span
                    v-else
                    class="inline-flex rounded-md bg-[var(--brand-accent)]/10 px-1.5 py-0.5 text-[10px] font-medium text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]"
                >
                    {{ t('Active') }}
                </span>
            </template>

            <template #cell-actions="{ row }">
                <div
                    v-if="userFromRow(row)"
                    class="flex justify-end"
                >
                    <Can permission="users.edit">
                        <RowActionsMenu
                            :actions="userActions(userFromRow(row)!)"
                        />
                    </Can>
                </div>
            </template>

            <template #footer>
                <MisPagination :pagination="users" />
            </template>
        </MisDataTable>

        <Dialog
            :open="!!editingUser"
            @update:open="(open) => !open && closeEdit()"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editMode === 'password'
                                ? t('Change password')
                                : t('Edit user')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editingUser?.name }}
                        <span
                            v-if="editingUser?.email"
                            class="text-muted-foreground"
                        >
                            · {{ editingUser.email }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="saveEdit">
                    <template v-if="editMode === 'profile'">
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <Label for="edit-name">{{ t('Name') }}</Label>
                                <Input
                                    id="edit-name"
                                    v-model="editForm.name"
                                    required
                                />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="edit-email">{{
                                    t('Email')
                                }}</Label>
                                <Input
                                    id="edit-email"
                                    v-model="editForm.email"
                                    type="email"
                                    required
                                />
                                <InputError :message="editForm.errors.email" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ t('Roles') }}</Label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="role in roles"
                                        :key="role.id"
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="
                                            editForm.roles.includes(role.name)
                                                ? 'is-on'
                                                : ''
                                        "
                                        @click="
                                            toggleEditRole(
                                                role.name,
                                                !editForm.roles.includes(
                                                    role.name,
                                                ),
                                            )
                                        "
                                    >
                                        {{ role.name }}
                                    </button>
                                </div>
                                <InputError :message="editForm.errors.roles" />
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <Label for="edit-password">{{
                                    t('New password')
                                }}</Label>
                                <PasswordInput
                                    id="edit-password"
                                    v-model="editForm.password"
                                    autocomplete="new-password"
                                    required
                                />
                                <InputError
                                    :message="editForm.errors.password"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="edit-password-confirmation">{{
                                    t('Confirm password')
                                }}</Label>
                                <PasswordInput
                                    id="edit-password-confirmation"
                                    v-model="editForm.password_confirmation"
                                    autocomplete="new-password"
                                    required
                                />
                            </div>
                        </div>
                    </template>

                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="editForm.processing"
                            @click="closeEdit"
                        >
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ t('Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </SettingsPage>
</template>
