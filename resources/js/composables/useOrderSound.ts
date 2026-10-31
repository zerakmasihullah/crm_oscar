import { getEcho } from '@/echo';

/** Public channel / event names matching OrderCreatedEvent. */
export const ORDER_SOUND_CHANNEL = 'my-channel';
export const ORDER_SOUND_EVENT = 'my-event';
export const ORDER_SOUND_PATH = '/sound.mp3';

type OrderSoundListener = (payload?: unknown) => void;

let mounted = false;
let mountCount = 0;
const listeners = new Set<OrderSoundListener>();

function playOrderSound(): void {
    try {
        const audio = new Audio(ORDER_SOUND_PATH);
        audio.volume = 0.7;
        void audio.play().catch((error) => {
            console.warn('Could not play order sound:', error);
        });
    } catch (error) {
        console.error('Error playing order sound:', error);
    }
}

function handleEvent(payload?: unknown): void {
    playOrderSound();
    listeners.forEach((listener) => {
        try {
            listener(payload);
        } catch (error) {
            console.error('Order sound listener failed:', error);
        }
    });
}

/**
 * Subscribe to new-order broadcasts and play sound.mp3.
 * Safe to call multiple times (ref-counted). Optional onEvent runs on each event.
 */
export function mountOrderSound(onEvent?: OrderSoundListener): () => void {
    mountCount += 1;
    if (onEvent) {
        listeners.add(onEvent);
    }

    const echo = getEcho();
    if (!echo) {
        console.warn('Echo unavailable; order sound not mounted.');
        return () => {
            unmountOrderSound(onEvent);
        };
    }

    if (!mounted) {
        echo.channel(ORDER_SOUND_CHANNEL).listen(
            `.${ORDER_SOUND_EVENT}`,
            handleEvent,
        );
        mounted = true;
    }

    return () => {
        unmountOrderSound(onEvent);
    };
}

export function unmountOrderSound(onEvent?: OrderSoundListener): void {
    if (onEvent) {
        listeners.delete(onEvent);
    }

    mountCount = Math.max(0, mountCount - 1);
    if (mountCount > 0) {
        return;
    }

    listeners.clear();

    const echo = getEcho();
    if (echo && mounted) {
        echo.channel(ORDER_SOUND_CHANNEL).stopListening(
            `.${ORDER_SOUND_EVENT}`,
            handleEvent,
        );
        echo.leave(ORDER_SOUND_CHANNEL);
    }
    mounted = false;
}

/** Vue-friendly helpers for onMounted / onUnmounted. */
export function useOrderSound(onEvent?: OrderSoundListener) {
    return {
        mount: () => mountOrderSound(onEvent),
        unmount: () => unmountOrderSound(onEvent),
        channel: ORDER_SOUND_CHANNEL,
        event: ORDER_SOUND_EVENT,
    };
}

export default useOrderSound;
