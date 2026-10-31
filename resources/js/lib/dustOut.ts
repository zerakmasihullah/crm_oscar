/**
 * Dust dissolve — clear, readable dissolve then optional row collapse.
 */

type DustPreset = {
    cols: number;
    rows: number;
    durationMs: number;
    maxParticles: number;
    skipChance: number;
    drift: number;
    lift: number;
    ash: boolean;
    particleMin: number;
    particleMax: number;
};

const PRESETS = {
    toast: {
        cols: 22,
        rows: 10,
        durationMs: 1000,
        maxParticles: 120,
        skipChance: 0.35,
        drift: 56,
        lift: 40,
        ash: true,
        particleMin: 1.2,
        particleMax: 2.4,
    },
    row: {
        cols: 18,
        rows: 6,
        durationMs: 900,
        maxParticles: 64,
        skipChance: 0.28,
        drift: 36,
        lift: 22,
        ash: true,
        particleMin: 2.2,
        particleMax: 3.8,
    },
} as const satisfies Record<string, DustPreset>;

const COLLAPSE_MS = 340;

export type DustOutOptions = {
    collapse?: boolean;
    color?: string;
    preset?: keyof typeof PRESETS;
};

export function findDustRow(from: HTMLElement | null): HTMLElement | null {
    if (!from) {
        return null;
    }

    const marked = from.closest(
        '[data-dust-row], .ui-list-row, tr',
    ) as HTMLElement | null;
    if (marked) {
        return marked;
    }

    let el: HTMLElement | null = from;
    while (el) {
        const parent = el.parentElement;
        if (!parent) {
            break;
        }

        if (
            parent.classList.contains('divide-y') ||
            parent.tagName === 'TBODY'
        ) {
            return el;
        }

        el = parent;
    }

    return null;
}

export function dustOut(
    el: HTMLElement,
    options: DustOutOptions = {},
): Promise<void> {
    const collapse = options.collapse ?? false;
    const presetKey = options.preset ?? (collapse ? 'row' : 'toast');
    const preset = PRESETS[presetKey];

    if (el.dataset.shattered === '1') {
        return Promise.resolve();
    }

    el.dataset.shattered = '1';

    const rect = el.getBoundingClientRect();
    if (rect.width < 8 || rect.height < 8) {
        return collapse ? collapseElement(el) : Promise.resolve();
    }

    const styles = window.getComputedStyle(el);
    const baseColor = options.color ?? sampleColor(el, styles, presetKey);

    spawnDustLayer(rect, baseColor, preset, presetKey === 'row');

    // Fade the real row so content → dust reads clearly (not an instant vanish).
    el.style.pointerEvents = 'none';
    el.style.transition = 'opacity 220ms ease';
    el.style.opacity = '0';

    const lead = collapse
        ? Math.min(420, Math.round(preset.durationMs * 0.48))
        : 0;

    return new Promise((resolve) => {
        window.setTimeout(() => {
            if (!collapse) {
                el.style.visibility = 'hidden';
                resolve();
                return;
            }

            void collapseElement(el).then(resolve);
        }, lead);
    });
}

function spawnDustLayer(
    rect: DOMRect,
    baseColor: string,
    preset: DustPreset,
    isRow: boolean,
): void {
    const layer = document.createElement('div');
    layer.setAttribute('aria-hidden', 'true');
    layer.style.cssText = [
        'position:fixed',
        `top:${rect.top}px`,
        `left:${rect.left}px`,
        `width:${rect.width}px`,
        `height:${rect.height}px`,
        'pointer-events:none',
        'z-index:2147483646',
        'overflow:visible',
    ].join(';');

    // Soft strip so the row silhouette is visible while it dissolves.
    if (isRow) {
        const strip = document.createElement('div');
        strip.style.cssText = [
            'position:absolute',
            'inset:0',
            `background:color-mix(in oklab, ${baseColor} 14%, transparent)`,
            'border-radius:0.5rem',
            `animation:row-dust-strip ${preset.durationMs}ms ease-out forwards`,
        ].join(';');
        layer.appendChild(strip);
    }

    if (preset.ash) {
        const ash = document.createElement('div');
        ash.style.cssText = [
            'position:absolute',
            'inset:-4%',
            `background:radial-gradient(ellipse at 50% 45%, ${withAlpha(baseColor, isRow ? 0.22 : 0.12)}, transparent 70%)`,
            `animation:row-dust-ash ${preset.durationMs}ms ease-out forwards`,
        ].join(';');
        layer.appendChild(ash);
    }

    const fragment = document.createDocumentFragment();
    const pieceW = rect.width / preset.cols;
    const pieceH = rect.height / preset.rows;
    const cx = rect.width / 2;
    const cy = rect.height / 2;
    let count = 0;

    for (let row = 0; row < preset.rows; row++) {
        for (let col = 0; col < preset.cols; col++) {
            if (
                count >= preset.maxParticles ||
                Math.random() < preset.skipChance
            ) {
                continue;
            }

            count += 1;

            const dust = document.createElement('span');
            const x = col * pieceW + pieceW * (0.25 + Math.random() * 0.5);
            const y = row * pieceH + pieceH * (0.25 + Math.random() * 0.5);
            const angle =
                Math.atan2(y - cy, x - cx) + (Math.random() - 0.5) * 0.85;
            const dist = 12 + Math.random() * preset.drift;
            const dx = Math.cos(angle) * dist;
            const dy =
                Math.sin(angle) * dist * 0.4 - Math.random() * preset.lift;
            const delay = Math.random() * (isRow ? 180 : 140);
            const size =
                preset.particleMin +
                Math.random() * (preset.particleMax - preset.particleMin);
            const shade = dustShade(baseColor, Math.random(), isRow);

            dust.style.cssText = [
                'position:absolute',
                `width:${size.toFixed(1)}px`,
                `height:${size.toFixed(1)}px`,
                `top:${(y - size / 2).toFixed(1)}px`,
                `left:${(x - size / 2).toFixed(1)}px`,
                `background:${shade}`,
                'border-radius:999px',
                `animation:row-dust-fly ${preset.durationMs}ms cubic-bezier(.12,.7,.2,1) ${delay.toFixed(0)}ms forwards`,
                `--dust-dx:${dx.toFixed(1)}px`,
                `--dust-dy:${dy.toFixed(1)}px`,
            ].join(';');

            fragment.appendChild(dust);
        }
    }

    layer.appendChild(fragment);
    document.body.appendChild(layer);

    window.setTimeout(() => {
        layer.remove();
    }, preset.durationMs + 120);
}

function collapseElement(el: HTMLElement): Promise<void> {
    return new Promise((resolve) => {
        const isRow = el.tagName === 'TR';
        const height = el.getBoundingClientRect().height;

        if (height < 1) {
            el.style.display = 'none';
            resolve();
            return;
        }

        const cs = getComputedStyle(el);

        el.style.overflow = 'hidden';
        el.style.boxSizing = 'border-box';
        el.style.height = `${height}px`;
        el.style.minHeight = '0';
        el.style.paddingTop = cs.paddingTop;
        el.style.paddingBottom = cs.paddingBottom;
        el.style.marginTop = cs.marginTop;
        el.style.marginBottom = cs.marginBottom;

        if (isRow) {
            for (const cell of el.querySelectorAll('td, th')) {
                const cellEl = cell as HTMLElement;
                cellEl.style.paddingTop = '0';
                cellEl.style.paddingBottom = '0';
                cellEl.style.borderBottomWidth = '0';
            }
        }

        void el.offsetHeight;

        el.style.transition = `height ${COLLAPSE_MS}ms cubic-bezier(.2,.8,.2,1), padding ${COLLAPSE_MS}ms cubic-bezier(.2,.8,.2,1), margin ${COLLAPSE_MS}ms cubic-bezier(.2,.8,.2,1)`;
        el.style.height = '0';
        el.style.paddingTop = '0';
        el.style.paddingBottom = '0';
        el.style.marginTop = '0';
        el.style.marginBottom = '0';
        el.style.borderBottomWidth = '0';

        window.setTimeout(() => {
            el.style.display = 'none';
            resolve();
        }, COLLAPSE_MS + 16);
    });
}

function sampleColor(
    el: HTMLElement,
    styles: CSSStyleDeclaration,
    preset: keyof typeof PRESETS,
): string {
    const bg = styles.backgroundColor;
    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
        return bg;
    }

    const type = el.getAttribute('data-type');
    if (type === 'success') {
        return (
            getComputedStyle(document.documentElement)
                .getPropertyValue('--brand-accent')
                .trim() || 'oklch(0.72 0.11 150)'
        );
    }
    if (type === 'error') {
        return 'oklch(0.7 0.13 25)';
    }
    if (type === 'warning') {
        return 'oklch(0.78 0.12 75)';
    }

    const root = getComputedStyle(document.documentElement);
    if (preset === 'row') {
        return (
            root.getPropertyValue('--foreground').trim() ||
            root.getPropertyValue('--muted-foreground').trim() ||
            '#52525b'
        );
    }

    return root.getPropertyValue('--muted-foreground').trim() || '#71717a';
}

function dustShade(base: string, t: number, strong: boolean): string {
    if (strong) {
        return `color-mix(in oklab, ${base} ${(55 + t * 35).toFixed(0)}%, #71717a)`;
    }

    return `color-mix(in oklab, ${base} ${(30 + t * 40).toFixed(0)}%, #a1a1aa)`;
}

function withAlpha(color: string, alpha: number): string {
    return `color-mix(in oklab, ${color} ${(alpha * 100).toFixed(0)}%, transparent)`;
}
