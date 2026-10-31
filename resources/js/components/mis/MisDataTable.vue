<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    Download,
    Filter,
    Printer,
    Search,
    Settings2,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import MisStatusBadge from '@/components/mis/MisStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

export type MisDataTableColumn = {
    key: string;
    label: string;
    class?: string;
    headerClass?: string;
    align?: 'start' | 'center' | 'end';
    sortable?: boolean;
    width?: string;
    sticky?: 'start' | 'end';
    mono?: boolean;
    truncate?: boolean;
    /** Allow hiding via Columns menu (default true except actions). */
    hideable?: boolean;
    type?: 'status' | 'text';
};

export type MisBulkConfirmRequest = {
    title: string;
    description: string;
    confirmLabel?: string;
    confirmVariant?: 'destructive' | 'default' | 'secondary' | 'outline' | 'ghost';
    onConfirm: () => void;
};

type SortDir = 'asc' | 'desc';

const props = withDefaults(
    defineProps<{
        columns: MisDataTableColumn[];
        rows: Array<Record<string, unknown>>;
        class?: string;
        emptyLabel?: string;
        loading?: boolean;
        density?: 'comfortable' | 'compact';
        rowKey?: string;
        stickyHeader?: boolean;
        zebra?: boolean;
        toolbar?: boolean;
        countLabel?: string;
        showIndex?: boolean;
        indexStart?: number;
        selectable?: boolean;
        /** Put row-selection toggle inside the options menu. */
        selectionToggle?: boolean;
        /** Show options menu (columns / density / selection). */
        columnToggle?: boolean;
        /** Show search icon that expands an input. */
        searchable?: boolean;
        search?: string;
        searchPlaceholder?: string;
        /** Show filter control that reveals the filters slot. */
        filterable?: boolean;
        /** Mark filter button as active (parent filter state). */
        filtersActive?: boolean;
        clickable?: boolean;
        /** Extra classes per row (e.g. status highlight). */
        rowClass?:
            | string
            | ((row: Record<string, unknown>, index: number) => string | undefined);
        selected?: Array<string | number>;
        /** Enable CSV export control (respects kitFeatures.exports). */
        exportable?: boolean;
        /** Enable print control (respects kitFeatures.exports). */
        printable?: boolean;
        /** Server export URL — used instead of client CSV when set. */
        exportHref?: string;
        /** Server print URL — used instead of client print when set. */
        printHref?: string;
        /** Client CSV/print filename and localStorage column persistence key. */
        exportFilename?: string;
        /** Override localStorage key for column/density preferences. */
        persistKey?: string;
    }>(),
    {
        class: undefined,
        emptyLabel: 'No results',
        loading: false,
        density: 'comfortable',
        rowKey: 'id',
        stickyHeader: true,
        zebra: false,
        toolbar: false,
        countLabel: undefined,
        showIndex: false,
        indexStart: 1,
        selectable: false,
        selectionToggle: false,
        columnToggle: false,
        searchable: false,
        search: undefined,
        searchPlaceholder: undefined,
        filterable: false,
        filtersActive: false,
        clickable: false,
        rowClass: undefined,
        selected: undefined,
        exportable: true,
        printable: true,
        exportHref: undefined,
        printHref: undefined,
        exportFilename: 'export',
        persistKey: undefined,
    },
);

const emit = defineEmits<{
    sort: [payload: { key: string; direction: SortDir }];
    rowClick: [payload: { row: Record<string, unknown>; index: number }];
    'update:selected': [keys: Array<string | number>];
    'update:search': [value: string];
}>();

const { t } = useTranslations();
const page = usePage();
const rootEl = ref<HTMLElement | null>(null);

const exportsEnabled = computed(
    () => page.props.kitFeatures?.exports !== false,
);
const showExport = computed(
    () => props.exportable && exportsEnabled.value,
);
const showPrint = computed(
    () => props.printable && exportsEnabled.value,
);

const sortKey = ref<string | null>(null);
const sortDir = ref<SortDir>('asc');
const internalSelected = ref<Array<string | number>>([]);
const selectionOn = ref(props.selectable);
const hiddenKeys = ref<string[]>([]);
const densityMode = ref<'comfortable' | 'compact'>(props.density);
const searchOpen = ref(Boolean(props.search?.trim()));
const filterOpen = ref(false);
const internalSearch = ref(props.search ?? '');
const pendingBulk = ref<MisBulkConfirmRequest | null>(null);
const bulkConfirming = ref(false);
const prefsReady = ref(false);

const persistenceKey = computed(() => {
    const key = (props.persistKey || props.exportFilename || 'table')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '-');

    return `kit.mis-table.${key || 'table'}`;
});

type TablePrefs = {
    hiddenKeys?: string[];
    density?: 'comfortable' | 'compact';
    selectionOn?: boolean;
};

function readPrefs(): TablePrefs | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(persistenceKey.value);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as TablePrefs;
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

function writePrefs(): void {
    if (!prefsReady.value || typeof window === 'undefined') {
        return;
    }

    try {
        const payload: TablePrefs = {
            hiddenKeys: hiddenKeys.value,
            density: densityMode.value,
            selectionOn: selectionOn.value,
        };
        window.localStorage.setItem(
            persistenceKey.value,
            JSON.stringify(payload),
        );
    } catch {
        // Ignore quota / private mode failures.
    }
}

let prefsWriteTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleWritePrefs(): void {
    if (prefsWriteTimer) {
        clearTimeout(prefsWriteTimer);
    }

    prefsWriteTimer = setTimeout(() => {
        prefsWriteTimer = null;
        writePrefs();
    }, 120);
}

function applyPrefs(): void {
    const prefs = readPrefs();
    const known = new Set(props.columns.map((column) => column.key));

    if (prefs?.hiddenKeys?.length) {
        hiddenKeys.value = prefs.hiddenKeys.filter((key) => known.has(key));
    }

    if (prefs?.density === 'compact' || prefs?.density === 'comfortable') {
        densityMode.value = prefs.density;
    }

    if (props.selectable && typeof prefs?.selectionOn === 'boolean') {
        selectionOn.value = prefs.selectionOn;
    } else if (props.selectable) {
        selectionOn.value = true;
    }

    prefsReady.value = true;
}

onMounted(() => {
    applyPrefs();
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    if (prefsWriteTimer) {
        clearTimeout(prefsWriteTimer);
    }
});

watch(persistenceKey, () => {
    prefsReady.value = false;
    hiddenKeys.value = [];
    densityMode.value = props.density;
    selectionOn.value = props.selectable;
    applyPrefs();
});

watch(hiddenKeys, () => scheduleWritePrefs(), { deep: true });
watch(densityMode, () => scheduleWritePrefs());
watch(selectionOn, () => scheduleWritePrefs());

const searchValue = computed({
    get: () => props.search ?? internalSearch.value,
    set: (value: string) => {
        internalSearch.value = value;
        emit('update:search', value);
    },
});

watch(
    () => props.selectable,
    (value) => {
        if (!props.selectionToggle) {
            selectionOn.value = value;
        } else if (value && !prefsReady.value) {
            selectionOn.value = true;
        }
    },
);

watch(
    () => props.density,
    (value) => {
        if (!prefsReady.value) {
            densityMode.value = value;
        }
    },
);

function sameKey(a: string | number, b: string | number): boolean {
    return String(a) === String(b);
}

function keyToken(key: string | number): string {
    return String(key);
}

const selectedKeys = computed({
    get: () => props.selected ?? internalSelected.value,
    set: (keys: Array<string | number>) => {
        internalSelected.value = keys;
        emit('update:selected', keys);
    },
});

const selectedKeySet = computed(() => {
    const set = new Set<string>();
    for (const key of selectedKeys.value) {
        set.add(keyToken(key));
    }
    return set;
});

const toggleableColumns = computed(() =>
    props.columns.filter((column) => {
        if (column.hideable === false) {
            return false;
        }
        if (column.hideable === true) {
            return true;
        }
        return column.key !== 'actions';
    }),
);

const visibleColumns = computed(() =>
    props.columns.filter((column) => !hiddenKeys.value.includes(column.key)),
);

const exportColumns = computed(() =>
    visibleColumns.value.filter((column) => column.key !== 'actions'),
);

const selectionActive = computed(
    () => props.selectable && selectionOn.value,
);

const displayRows = computed(() => {
    if (!sortKey.value) {
        return props.rows;
    }

    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const rows = props.rows.slice();

    rows.sort((a, b) => {
        const av = a[key];
        const bv = b[key];

        if (av == null && bv == null) {
            return 0;
        }
        if (av == null) {
            return 1;
        }
        if (bv == null) {
            return -1;
        }

        if (typeof av === 'number' && typeof bv === 'number') {
            return (av - bv) * dir;
        }

        return (
            String(av).localeCompare(String(bv), undefined, {
                numeric: true,
                sensitivity: 'base',
            }) * dir
        );
    });

    return rows;
});

const selectableRowKeys = computed(() => {
    const keys: Array<string | number> = [];
    for (const row of displayRows.value) {
        const key = rowKeyOf(row);
        if (key != null) {
            keys.push(key);
        }
    }
    return keys;
});

const selectableRows = computed(() =>
    displayRows.value.filter((row) => rowKeyOf(row) != null),
);

const allSelected = computed(() => {
    const keys = selectableRowKeys.value;
    return (
        keys.length > 0 &&
        keys.every((key) => selectedKeySet.value.has(keyToken(key)))
    );
});

const someSelected = computed(() => {
    const keys = selectableRowKeys.value;
    let count = 0;
    for (const key of keys) {
        if (selectedKeySet.value.has(keyToken(key))) {
            count += 1;
        }
    }
    return count > 0 && count < keys.length;
});

const selectedRows = computed(() =>
    props.rows.filter((row) => {
        const key = rowKeyOf(row);
        return key != null && selectedKeySet.value.has(keyToken(key));
    }),
);

const colSpan = computed(
    () =>
        visibleColumns.value.length +
        (props.showIndex ? 1 : 0) +
        (selectionActive.value ? 1 : 0),
);

const showToolbar = computed(
    () =>
        props.toolbar ||
        props.columnToggle ||
        props.selectionToggle ||
        props.searchable ||
        props.filterable ||
        showExport.value ||
        showPrint.value,
);

watch(
    () => props.search,
    (value) => {
        if (value != null) {
            internalSearch.value = value;
            if (value.trim()) {
                searchOpen.value = true;
            }
        }
    },
);

function openSearch(): void {
    searchOpen.value = true;
    void nextTick(() => {
        const input = rootEl.value?.querySelector<HTMLInputElement>(
            'input[type="search"]',
        );
        input?.focus();
        input?.select();
    });
}

function closeSearch(): void {
    searchOpen.value = false;
    if (searchValue.value) {
        searchValue.value = '';
    }
}

function toggleFilter(): void {
    filterOpen.value = !filterOpen.value;
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') {
        return;
    }

    if (filterOpen.value) {
        filterOpen.value = false;
        event.preventDefault();
        return;
    }

    if (searchOpen.value) {
        closeSearch();
        event.preventDefault();
    }
}

watch(
    () => props.rows,
    () => {
        const valid = new Set(
            props.rows
                .map((row) => rowKeyOf(row))
                .filter((key): key is string | number => key != null)
                .map(String),
        );
        selectedKeys.value = selectedKeys.value.filter((key) =>
            valid.has(String(key)),
        );
    },
);

function cellValue(row: Record<string, unknown>, key: string): string {
    const value = row[key];

    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function alignClass(align?: 'start' | 'center' | 'end'): string {
    if (align === 'center') {
        return 'text-center';
    }
    if (align === 'end') {
        return 'text-end';
    }
    return 'text-start';
}

function rowKeyOf(row: Record<string, unknown>): string | number | null {
    if (!props.rowKey || row[props.rowKey] == null) {
        return null;
    }
    const value = row[props.rowKey];
    if (typeof value === 'string' || typeof value === 'number') {
        return value;
    }
    return String(value);
}

function rowId(
    row: Record<string, unknown>,
    index: number,
): string | number {
    return rowKeyOf(row) ?? index;
}

function stickyClass(sticky?: 'start' | 'end'): string {
    if (sticky === 'start') {
        return 'sticky start-0 z-[1] bg-card';
    }
    if (sticky === 'end') {
        return 'sticky end-0 z-[1] bg-card';
    }
    return '';
}

function toggleSort(column: MisDataTableColumn): void {
    if (!column.sortable) {
        return;
    }

    if (sortKey.value === column.key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = column.key;
        sortDir.value = 'asc';
    }

    emit('sort', { key: column.key, direction: sortDir.value });
}

function isSelected(row: Record<string, unknown>): boolean {
    const key = rowKeyOf(row);
    return key != null && selectedKeySet.value.has(keyToken(key));
}

function toggleRow(row: Record<string, unknown>, checked: boolean): void {
    const key = rowKeyOf(row);
    if (key == null) {
        return;
    }

    if (checked) {
        if (!selectedKeySet.value.has(keyToken(key))) {
            selectedKeys.value = [...selectedKeys.value, key];
        }
        return;
    }

    selectedKeys.value = selectedKeys.value.filter(
        (item) => !sameKey(item, key),
    );
}

function toggleAll(checked: boolean): void {
    selectedKeys.value = checked ? [...selectableRowKeys.value] : [];
}

function clearSelection(): void {
    selectedKeys.value = [];
}

function setSelectionMode(on: boolean): void {
    selectionOn.value = on;
    if (!on) {
        clearSelection();
    }
}

function isColumnVisible(key: string): boolean {
    return !hiddenKeys.value.includes(key);
}

function setColumnVisible(key: string, visible: boolean): void {
    if (visible) {
        hiddenKeys.value = hiddenKeys.value.filter((item) => item !== key);
        return;
    }

    const visibleToggleable = toggleableColumns.value.filter(
        (column) => !hiddenKeys.value.includes(column.key),
    );

    if (
        visibleToggleable.length <= 1 &&
        visibleToggleable.some((column) => column.key === key)
    ) {
        return;
    }

    if (!hiddenKeys.value.includes(key)) {
        hiddenKeys.value = [...hiddenKeys.value, key];
    }
}

function onRowClick(event: MouseEvent, row: Record<string, unknown>, index: number): void {
    const target = event.target as HTMLElement | null;
    if (
        target?.closest(
            'button, a, input, label, [role="checkbox"], [data-row-actions], [data-no-row-click]',
        )
    ) {
        return;
    }

    if (!props.clickable) {
        return;
    }

    emit('rowClick', { row, index });
}

function csvEscape(value: string): string {
    if (/[",\n\r]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }
    return value;
}

function exportRows(): Array<Record<string, unknown>> {
    if (selectionActive.value && selectedKeys.value.length > 0) {
        return displayRows.value.filter((row) => {
            const key = rowKeyOf(row);
            return key != null && selectedKeySet.value.has(keyToken(key));
        });
    }

    return displayRows.value;
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function runExport(): void {
    if (props.exportHref && !(selectionActive.value && selectedKeys.value.length > 0)) {
        window.location.assign(props.exportHref);
        return;
    }

    const rows = exportRows();
    const headers = exportColumns.value.map((column) => column.label);
    const lines = [
        headers.map(csvEscape).join(','),
        ...rows.map((row) =>
            exportColumns.value
                .map((column) => csvEscape(cellValue(row, column.key)))
                .join(','),
        ),
    ];

    const blob = new Blob([`\uFEFF${lines.join('\n')}`], {
        type: 'text/csv;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.exportFilename || 'export'}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function runPrint(): void {
    if (
        props.printHref &&
        !(selectionActive.value && selectedKeys.value.length > 0)
    ) {
        window.open(props.printHref, '_blank', 'noopener,noreferrer');
        return;
    }

    const rows = exportRows();
    const columns = exportColumns.value;
    const sectionTitle =
        (typeof props.exportFilename === 'string' &&
        props.exportFilename.trim() !== '' &&
        props.exportFilename !== 'export'
            ? props.exportFilename
                  .replace(/[-_]+/g, ' ')
                  .replace(/\b\w/g, (char) => char.toUpperCase())
            : '') ||
        document.title ||
        t('Print');
    const printedAt = new Date().toLocaleString();
    const appName =
        typeof page.props.name === 'string' && page.props.name.trim() !== ''
            ? page.props.name
            : 'Oscar';
    const logo =
        typeof page.props.logo === 'string' && page.props.logo.trim() !== ''
            ? page.props.logo
            : null;

    const headCells = columns
        .map(
            (column) =>
                `<th scope="col">${escapeHtml(column.label)}</th>`,
        )
        .join('');

    const bodyRows =
        rows.length === 0
            ? `<tr><td class="empty" colspan="${Math.max(columns.length, 1)}">${escapeHtml(props.emptyLabel || t('No results'))}</td></tr>`
            : rows
                  .map((row) => {
                      const cells = columns
                          .map(
                              (column) =>
                                  `<td>${escapeHtml(cellValue(row, column.key))}</td>`,
                          )
                          .join('');
                      return `<tr>${cells}</tr>`;
                  })
                  .join('');

    const brand =
        page.props.themeColors &&
        typeof page.props.themeColors === 'object' &&
        typeof (page.props.themeColors as { brand?: unknown }).brand ===
            'string' &&
        (page.props.themeColors as { brand: string }).brand.trim() !== ''
            ? (page.props.themeColors as { brand: string }).brand
            : '#171717';

    const markHtml = logo
        ? `<img class="logo" src="${escapeHtml(logo)}" alt="${escapeHtml(appName)}" />`
        : `<span class="logo-fallback" aria-hidden="true">${escapeHtml(appName.slice(0, 1).toUpperCase())}</span>`;

    const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapeHtml(sectionTitle)} · ${escapeHtml(appName)}</title>
<style>
  :root {
    color-scheme: light;
    --brand: ${escapeHtml(brand)};
    --ink: #0a0a0a;
    --muted: #737373;
    --line: #e5e5e5;
    --paper: #ffffff;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    color: var(--ink);
    background: #f5f5f5;
    font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  .page {
    min-height: 100vh;
    padding: 36px 16px 48px;
  }
  .sheet {
    max-width: 880px;
    margin: 0 auto;
    padding: 28px 32px 32px;
    background: var(--paper);
    border: 1px solid #ebebeb;
    border-radius: 20px;
    box-shadow: 0 18px 50px -28px rgba(0, 0, 0, 0.28);
  }
  .mast {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px 20px;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--line);
  }
  .identity {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
  }
  .logo,
  .logo-fallback {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    flex-shrink: 0;
  }
  .logo {
    object-fit: contain;
    background: #fafafa;
    border: 1px solid var(--line);
  }
  .logo-fallback {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--brand);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
  }
  .org {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .actions {
    grid-column: 2;
    grid-row: 1 / span 2;
    align-self: start;
  }
  .actions button {
    appearance: none;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: #fff;
    color: var(--ink);
    font: inherit;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.45rem 0.95rem;
    cursor: pointer;
  }
  .actions button:hover { background: #fafafa; }
  .section {
    grid-column: 1;
    margin: 0;
    font-size: 1.35rem;
    font-weight: 650;
    letter-spacing: -0.035em;
    line-height: 1.2;
  }
  .meta {
    grid-column: 1;
    margin: 0;
    color: var(--muted);
    font-size: 0.78rem;
  }
  .table-wrap {
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  th, td {
    padding: 0.8rem 0.95rem;
    text-align: start;
    vertical-align: top;
    border-bottom: 1px solid var(--line);
  }
  th {
    background: #fafafa;
    color: var(--muted);
    font-size: 0.68rem;
    font-weight: 650;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  tbody tr:last-child td { border-bottom: 0; }
  .empty {
    color: var(--muted);
    text-align: center;
    padding: 2rem 1rem;
  }
  @media print {
    body { background: #fff; }
    .page { padding: 0; }
    .sheet {
      max-width: none;
      padding: 0;
      border: 0;
      border-radius: 0;
      box-shadow: none;
    }
    .no-print { display: none !important; }
    .mast {
      grid-template-columns: 1fr;
      padding-bottom: 14px;
      margin-bottom: 16px;
    }
    .table-wrap { border-radius: 0; }
  }
</style>
</head>
<body>
  <div class="page">
    <div class="sheet">
      <header class="mast">
        <div class="identity">
          ${markHtml}
          <p class="org">${escapeHtml(appName)}</p>
        </div>
        <div class="actions no-print">
          <button type="button" onclick="window.print()">${escapeHtml(t('Print'))}</button>
        </div>
        <h1 class="section">${escapeHtml(sectionTitle)}</h1>
        <p class="meta">${escapeHtml(printedAt)} · ${escapeHtml(String(rows.length))} ${escapeHtml(t('records'))}</p>
      </header>
      <div class="table-wrap">
        <table>
          <thead><tr>${headCells}</tr></thead>
          <tbody>${bodyRows}</tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>`;

    const popup = window.open('about:blank', '_blank');
    if (!popup) {
        return;
    }

    popup.document.open();
    popup.document.write(html);
    popup.document.close();
    popup.focus();
}

function requestBulkConfirm(request: MisBulkConfirmRequest): void {
    pendingBulk.value = request;
}

function confirmPendingBulk(): void {
    if (!pendingBulk.value || bulkConfirming.value) {
        return;
    }

    const action = pendingBulk.value;
    bulkConfirming.value = true;
    pendingBulk.value = null;

    try {
        action.onConfirm();
    } finally {
        bulkConfirming.value = false;
    }
}

defineExpose({
    clearSelection,
    selectedRows,
    setSelectionMode,
    requestBulkConfirm,
});
</script>

<template>
    <div
        ref="rootEl"
        :class="
            cn(
                'mis-data-table relative overflow-hidden rounded-[1.25rem] border border-border/50 bg-card shadow-[0_12px_40px_-28px_rgba(15,23,42,0.28)] dark:border-white/10',
                $props.class,
            )
        "
    >
        <div
            v-if="showToolbar || $slots.toolbar || $slots.actions || columnToggle || selectionToggle"
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5"
        >
            <div class="min-w-0">
                <slot name="toolbar">
                    <p
                        v-if="rows.length > 0 && countLabel"
                        class="text-sm text-muted-foreground"
                    >
                        <span class="font-medium text-foreground tabular-nums">
                            {{ rows.length }}
                        </span>
                        {{ ' ' + countLabel }}
                    </p>
                </slot>
            </div>
            <div class="flex items-center gap-0.5 rounded-xl bg-muted/40 p-0.5">
                <template v-if="searchable">
                    <div
                        v-if="searchOpen"
                        class="flex items-center gap-1 pe-0.5"
                    >
                        <Input
                            :model-value="searchValue"
                            type="search"
                            class="h-8 w-40 border-0 bg-transparent shadow-none focus-visible:ring-0 sm:w-52"
                            :placeholder="
                                searchPlaceholder ?? t('Search…')
                            "
                            @update:model-value="
                                (value) =>
                                    (searchValue = String(value ?? ''))
                            "
                            @keydown.escape.stop.prevent="closeSearch"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-8 rounded-lg"
                            :title="t('Clear')"
                            :aria-label="t('Clear')"
                            @click="closeSearch"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                    <Button
                        v-else
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-8 rounded-lg"
                        :title="t('Search')"
                        :aria-label="t('Search')"
                        @click="openSearch"
                    >
                        <Search class="size-4" />
                    </Button>
                </template>

                <div
                    v-if="filterable"
                    class="relative"
                    :class="filterOpen ? 'z-40' : ''"
                >
                    <Button
                        type="button"
                        size="icon"
                        :variant="filterOpen || filtersActive ? 'secondary' : 'ghost'"
                        class="relative size-8 rounded-lg"
                        :title="t('Filter')"
                        :aria-label="t('Filter')"
                        :aria-pressed="filterOpen"
                        @click="toggleFilter"
                    >
                        <Filter class="size-4" />
                        <span
                            v-if="filtersActive"
                            class="absolute end-1.5 top-1.5 size-1.5 rounded-full bg-primary"
                        />
                    </Button>

                    <div
                        v-if="filterOpen && $slots.filters"
                        class="absolute end-0 top-[calc(100%+0.4rem)] z-50 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-border/60 bg-popover p-3 shadow-lg"
                        @click.stop
                        @pointerdown.stop
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {{ t('Filter') }}
                            </p>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-7 rounded-md"
                                :title="t('Clear')"
                                :aria-label="t('Close')"
                                @click="filterOpen = false"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </div>
                        <slot
                            name="filters"
                            :close="() => (filterOpen = false)"
                        />
                    </div>
                </div>

                <Button
                    v-if="showExport"
                    type="button"
                    size="icon"
                    variant="ghost"
                    class="size-8 rounded-lg"
                    :title="t('Export')"
                    :aria-label="t('Export')"
                    :disabled="displayRows.length === 0 && !exportHref"
                    @click="runExport"
                >
                    <Download class="size-4" />
                </Button>

                <Button
                    v-if="showPrint"
                    type="button"
                    size="icon"
                    variant="ghost"
                    class="size-8 rounded-lg"
                    :title="t('Print')"
                    :aria-label="t('Print')"
                    :disabled="displayRows.length === 0 && !printHref"
                    @click="runPrint"
                >
                    <Printer class="size-4" />
                </Button>

                <DropdownMenu
                    v-if="
                        columnToggle ||
                        (selectionToggle && selectable)
                    "
                >
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-8 rounded-lg"
                            :title="t('Options')"
                            :aria-label="t('Options')"
                        >
                            <Settings2 class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="z-[80] w-56">
                        <template v-if="selectionToggle && selectable">
                            <DropdownMenuLabel>{{
                                t('Selection')
                            }}</DropdownMenuLabel>
                            <DropdownMenuItem
                                class="gap-2"
                                @select.prevent="
                                    setSelectionMode(!selectionOn)
                                "
                            >
                                <span
                                    class="flex size-4 items-center justify-center rounded-[4px] border border-input"
                                    :class="
                                        selectionOn
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background'
                                    "
                                >
                                    <Check
                                        v-if="selectionOn"
                                        class="size-3"
                                    />
                                </span>
                                {{ t('Show checkboxes') }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                        </template>

                        <template v-if="columnToggle && toggleableColumns.length">
                            <DropdownMenuLabel>{{
                                t('Columns')
                            }}</DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="column in toggleableColumns"
                                :key="column.key"
                                class="gap-2"
                                @select.prevent="
                                    setColumnVisible(
                                        column.key,
                                        !isColumnVisible(column.key),
                                    )
                                "
                            >
                                <span
                                    class="flex size-4 items-center justify-center rounded-[4px] border border-input"
                                    :class="
                                        isColumnVisible(column.key)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background'
                                    "
                                >
                                    <Check
                                        v-if="isColumnVisible(column.key)"
                                        class="size-3"
                                    />
                                </span>
                                {{ column.label }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                        </template>

                        <DropdownMenuLabel>{{ t('Display') }}</DropdownMenuLabel>
                        <DropdownMenuItem
                            class="gap-2"
                            @select.prevent="
                                densityMode =
                                    densityMode === 'compact'
                                        ? 'comfortable'
                                        : 'compact'
                            "
                        >
                            <span
                                class="flex size-4 items-center justify-center rounded-[4px] border border-input"
                                :class="
                                    densityMode === 'compact'
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <Check
                                    v-if="densityMode === 'compact'"
                                    class="size-3"
                                />
                            </span>
                            {{ t('Compact rows') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <div
                    v-if="$slots.actions"
                    class="ms-0.5 flex items-center border-s border-border/50 ps-0.5"
                >
                    <slot name="actions" />
                </div>
            </div>
        </div>

        <div
            v-if="filterOpen"
            class="fixed inset-0 z-30"
            aria-hidden="true"
            @pointerdown="filterOpen = false"
        />

        <div
            v-if="loading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-background/55 backdrop-blur-[1px]"
        >
            <Spinner class="size-6" />
        </div>

        <div class="mis-data-table-scroll relative overflow-x-auto">
            <table class="w-full min-w-[36rem] border-separate border-spacing-0 text-sm">
                <thead>
                    <tr
                        :class="
                            cn(
                                stickyHeader && 'sticky top-0 z-[2]',
                            )
                        "
                    >
                        <th
                            v-if="selectionActive"
                            scope="col"
                            class="w-10 bg-muted/25 px-3 py-3 backdrop-blur-md"
                            data-no-row-click
                        >
                            <Checkbox
                                :model-value="
                                    allSelected
                                        ? true
                                        : someSelected
                                          ? 'indeterminate'
                                          : false
                                "
                                :aria-label="t('Select all')"
                                @update:model-value="
                                    (value) => toggleAll(value === true)
                                "
                            />
                        </th>
                        <th
                            v-if="showIndex"
                            scope="col"
                            class="w-12 bg-muted/25 px-3 py-3 text-start text-xs font-medium text-muted-foreground backdrop-blur-md"
                        >
                            #
                        </th>
                        <th
                            v-for="column in visibleColumns"
                            :key="column.key"
                            scope="col"
                            :style="
                                column.width ? { width: column.width } : undefined
                            "
                            :class="
                                cn(
                                    'bg-muted/25 text-xs font-medium text-muted-foreground backdrop-blur-md',
                                    densityMode === 'compact'
                                        ? 'px-3 py-2.5'
                                        : 'px-4 py-3',
                                    alignClass(column.align),
                                    stickyClass(column.sticky),
                                    column.sticky && 'bg-muted/35',
                                    column.headerClass,
                                    column.class,
                                    column.sortable &&
                                        'cursor-pointer select-none transition-colors hover:text-foreground',
                                )
                            "
                            @click="toggleSort(column)"
                        >
                            <span
                                class="inline-flex items-center gap-1.5"
                                :class="
                                    column.align === 'end'
                                        ? 'w-full justify-end'
                                        : column.align === 'center'
                                          ? 'w-full justify-center'
                                          : 'justify-start'
                                "
                            >
                                <span v-if="column.key !== 'actions'">{{
                                    column.label
                                }}</span>
                                <span v-else class="sr-only">{{
                                    column.label
                                }}</span>
                                <template v-if="column.sortable">
                                    <ArrowUp
                                        v-if="
                                            sortKey === column.key &&
                                            sortDir === 'asc'
                                        "
                                        class="size-3.5 text-foreground"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sortKey === column.key &&
                                            sortDir === 'desc'
                                        "
                                        class="size-3.5 text-foreground"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 opacity-35"
                                    />
                                </template>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && displayRows.length === 0">
                        <td
                            :colspan="colSpan"
                            class="px-4 py-16 text-center text-muted-foreground"
                        >
                            <slot name="empty">
                                <div class="mx-auto max-w-xs space-y-1">
                                    <p
                                        class="text-sm font-medium text-foreground/80"
                                    >
                                        {{ emptyLabel }}
                                    </p>
                                </div>
                            </slot>
                        </td>
                    </tr>
                    <tr
                        v-for="(row, rowIndex) in displayRows"
                        :key="rowId(row, rowIndex)"
                        data-dust-row
                        :class="
                            cn(
                                'mis-data-table-row group transition-colors duration-150',
                                'hover:bg-muted/30',
                                zebra && rowIndex % 2 === 1 && 'bg-muted/10',
                                clickable && 'cursor-pointer',
                                isSelected(row) && 'bg-primary/[0.05]',
                                typeof rowClass === 'function'
                                    ? rowClass(row, rowIndex)
                                    : rowClass,
                            )
                        "
                        @click="onRowClick($event, row, rowIndex)"
                    >
                        <td
                            v-if="selectionActive"
                            class="w-10 px-3 py-3.5 align-middle"
                            data-no-row-click
                            @click.stop
                        >
                            <Checkbox
                                :model-value="isSelected(row)"
                                :disabled="rowKeyOf(row) == null"
                                :aria-label="t('Select row')"
                                @update:model-value="
                                    (value) => toggleRow(row, value === true)
                                "
                            />
                        </td>
                        <td
                            v-if="showIndex"
                            class="w-12 px-3 py-3.5 align-middle text-xs tabular-nums text-muted-foreground/80"
                        >
                            {{ indexStart + rowIndex }}
                        </td>
                        <td
                            v-for="column in visibleColumns"
                            :key="column.key"
                            :class="
                                cn(
                                    'align-middle',
                                    densityMode === 'compact'
                                        ? 'px-3 py-2.5'
                                        : 'px-4 py-3.5',
                                    alignClass(column.align),
                                    stickyClass(column.sticky),
                                    column.sticky &&
                                        'bg-card group-hover:bg-muted/30',
                                    isSelected(row) &&
                                        column.sticky &&
                                        'bg-primary/[0.05] group-hover:bg-primary/[0.07]',
                                    column.mono &&
                                        'font-mono text-[13px] tabular-nums',
                                    column.truncate && 'max-w-[16rem]',
                                    column.class,
                                )
                            "
                        >
                            <div
                                :class="
                                    cn(
                                        column.truncate && 'truncate',
                                        column.align === 'end' && 'ms-auto',
                                    )
                                "
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :row="row"
                                    :value="row[column.key]"
                                    :index="rowIndex"
                                >
                                    <MisStatusBadge
                                        v-if="
                                            (column.type === 'status' ||
                                                column.key === 'status') &&
                                            row[column.key] != null &&
                                            String(row[column.key]) !== ''
                                        "
                                        :status="String(row[column.key])"
                                    />
                                    <span v-else>{{
                                        cellValue(row, column.key)
                                    }}</span>
                                </slot>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-border/40 px-4 py-3 sm:px-5"
            :class="
                selectionActive && selectedKeys.length > 0 ? 'pb-16' : undefined
            "
        >
            <slot name="footer" />
        </div>
        <div
            v-else-if="selectionActive && selectedKeys.length > 0"
            class="h-14"
            aria-hidden="true"
        />

        <Transition name="mis-bulk">
            <div
                v-if="selectionActive && selectedKeys.length > 0"
                class="mis-bulk-bar pointer-events-none absolute inset-x-0 bottom-3 z-20 px-3 sm:px-4"
            >
                <div
                    class="pointer-events-auto mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/60 bg-card/95 px-3 py-2.5 shadow-[0_18px_50px_-28px_rgba(15,23,42,0.55)] ring-1 ring-black/5 backdrop-blur-xl dark:border-white/10 dark:bg-card/95 dark:ring-white/10"
                >
                    <div class="flex min-w-0 items-center gap-2.5 ps-0.5">
                        <span
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-primary px-2 text-[12px] font-semibold tabular-nums text-primary-foreground shadow-[0_8px_18px_-10px_color-mix(in_oklab,var(--primary)_70%,transparent)]"
                        >
                            {{ selectedKeys.length }}
                        </span>
                        <div class="min-w-0 leading-tight">
                            <p class="text-sm font-medium tracking-tight">
                                {{
                                    selectedKeys.length === 1
                                        ? t('1 selected')
                                        : t(':count selected', {
                                              count: String(
                                                  selectedKeys.length,
                                              ),
                                          })
                                }}
                            </p>
                            <button
                                v-if="!allSelected && selectableRows.length > 0"
                                type="button"
                                class="text-[11px] font-medium text-primary hover:underline"
                                @click="toggleAll(true)"
                            >
                                {{
                                    t('Select all :count', {
                                        count: String(selectableRows.length),
                                    })
                                }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <Button
                            v-if="showExport"
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="mis-bulk-btn h-8 gap-1.5 rounded-xl px-2.5"
                            @click="runExport"
                        >
                            <Download class="size-3.5" />
                            <span class="hidden sm:inline">{{
                                t('Export')
                            }}</span>
                        </Button>

                        <div
                            v-if="$slots.bulk"
                            class="mis-bulk-actions flex flex-wrap items-center gap-1.5"
                        >
                            <slot
                                name="bulk"
                                :selected="selectedRows"
                                :selected-keys="selectedKeys"
                                :clear="clearSelection"
                                :confirm="requestBulkConfirm"
                            />
                        </div>

                        <div
                            class="ms-0.5 h-5 w-px bg-border/70"
                            aria-hidden="true"
                        />

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="h-8 gap-1.5 rounded-xl px-2.5 text-muted-foreground"
                            @click="clearSelection"
                        >
                            <X class="size-3.5" />
                            <span class="hidden sm:inline">{{
                                t('Clear')
                            }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>

        <Dialog
            :open="pendingBulk !== null"
            @update:open="(open) => !open && (pendingBulk = null)"
        >
            <DialogContent v-if="pendingBulk" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ pendingBulk.title }}</DialogTitle>
                    <DialogDescription>
                        {{ pendingBulk.description }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <DialogClose as-child>
                        <Button variant="ghost" :disabled="bulkConfirming">
                            {{ t('Cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        :variant="pendingBulk.confirmVariant ?? 'destructive'"
                        class="min-w-28"
                        :disabled="bulkConfirming"
                        @click="confirmPendingBulk"
                    >
                        {{
                            bulkConfirming
                                ? t('Processing...')
                                : (pendingBulk.confirmLabel ?? t('Confirm'))
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
