import { dustOut } from '@/lib/dustOut';

/**
 * Dust dissolve: when a sonner toast is removed, it crumbles into fine particles.
 */
function dustToast(el: HTMLElement): void {
    void dustOut(el, { collapse: false, preset: 'toast' });
}

export function installToastShatter(): () => void {
    const seen = new WeakSet<Element>();

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (
                mutation.type === 'attributes' &&
                mutation.attributeName === 'data-removed'
            ) {
                const el = mutation.target as HTMLElement;
                if (
                    el.matches?.('[data-sonner-toast]') &&
                    el.getAttribute('data-removed') === 'true' &&
                    !seen.has(el)
                ) {
                    seen.add(el);
                    dustToast(el);
                }
            }

            for (const node of mutation.addedNodes) {
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                if (
                    node.matches?.('[data-sonner-toast][data-removed="true"]') &&
                    !seen.has(node)
                ) {
                    seen.add(node);
                    dustToast(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        subtree: true,
        attributes: true,
        attributeFilter: ['data-removed'],
        childList: true,
    });

    return () => observer.disconnect();
}
