import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type EchoInstance = InstanceType<typeof Echo>;

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: EchoInstance;
    }
}

let echoInstance: EchoInstance | null = null;

export function getEcho(): EchoInstance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const key =
        (import.meta.env.VITE_PUSHER_APP_KEY as string | undefined) ||
        '0805eb292f113fc0e485';
    const cluster =
        (import.meta.env.VITE_PUSHER_APP_CLUSTER as string | undefined) || 'eu';

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: true,
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echoInstance;

    return echoInstance;
}

// Eager init for admin shell (safe no-op if key missing / SSR).
getEcho();

export default getEcho;
