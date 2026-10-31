import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            logo: string | null;
            favicon: string | null;
            themeColors: {
                brand: string;
                secondary: string;
                accent: string;
                accentDark: string;
                backgroundLight: string;
                backgroundDark: string;
                radius: 'soft' | 'medium' | 'sharp';
                density: 'comfortable' | 'compact';
                sidebarActive: 'pill' | 'accent' | 'soft';
                shell:
                    | 'sidebar-floating'
                    | 'sidebar-inset'
                    | 'sidebar-flush'
                    | 'header';
                sidebar_active?: 'pill' | 'accent' | 'soft';
            };
            kitFeatures?: {
                registration?: boolean;
                reset_password?: boolean;
                email_verification?: boolean;
                multilingual?: boolean;
                translations_ui?: boolean;
                exports?: boolean;
                excel_exports?: boolean;
                api?: boolean;
                tours?: boolean;
                seo?: boolean;
                attachments?: boolean;
                audit?: boolean;
                ai?: boolean;
            };
            aiSettings?: {
                enabled: boolean;
                default_model_id: string | null;
                models: Array<{
                    id: string;
                    label: string;
                    provider: string;
                    model: string;
                }>;
                features: Record<string, boolean>;
                actions: Array<{ id: string; label: string }>;
                max_input_chars: number;
                max_instruction_chars: number;
                panel_size?: string;
                panel_sizes?: Array<{
                    id: string;
                    label: string;
                    width: number;
                    prompt_rows: number;
                    result_max_h: number;
                }>;
            } | null;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            dir: string;
            calendarSystem: 'gregorian' | 'jalali' | 'hijri';
            locales: Array<{
                code: string;
                name: string;
                native: string;
                dir: string;
            }>;
            translations: Record<string, string>;
            flash: {
                success?: string | null;
                error?: string | null;
            };
            notifications: { unread_count: number } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
