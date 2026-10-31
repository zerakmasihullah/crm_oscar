export type NotificationType = 'info' | 'success' | 'warning' | 'error';

export type AppNotification = {
    id: string;
    title: string;
    body: string | null;
    type: NotificationType;
    action_url: string | null;
    read_at: string | null;
    created_at: string | null;
};
