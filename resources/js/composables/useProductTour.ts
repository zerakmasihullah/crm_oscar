import { driver, type DriveStep } from 'driver.js';
import 'driver.js/dist/driver.css';
import { useTranslations } from '@/composables/useTranslations';

const TOUR_STORAGE_KEY = 'kit.tour.admin-shell.dismissed';

export function useProductTour() {
    const { t } = useTranslations();

    function hasDismissed(): boolean {
        try {
            return localStorage.getItem(TOUR_STORAGE_KEY) === '1';
        } catch {
            return true;
        }
    }

    function dismiss(): void {
        try {
            localStorage.setItem(TOUR_STORAGE_KEY, '1');
        } catch {
            // ignore
        }
    }

    function startAdminShellTour(force = false): void {
        if (!force && hasDismissed()) {
            return;
        }

        const candidates: DriveStep[] = [
            {
                element: '[data-tour="app-sidebar"]',
                popover: {
                    title: t('Navigation'),
                    description: t(
                        'Use the sidebar to move between dashboard, modules, and administration.',
                    ),
                    side: 'right',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="app-header"]',
                popover: {
                    title: t('Page header'),
                    description: t(
                        'Switch language, check notifications, and open your account menu here.',
                    ),
                    side: 'bottom',
                    align: 'end',
                },
            },
            {
                element: '[data-tour="sidebar-nav"]',
                popover: {
                    title: t('Settings'),
                    description: t(
                        'Profile, security, users, roles, backups, login logs, and system logs live under Settings.',
                    ),
                    side: 'right',
                    align: 'start',
                },
            },
        ];

        const steps = candidates.filter((step) => {
            if (typeof step.element !== 'string') {
                return true;
            }

            return Boolean(document.querySelector(step.element));
        });

        if (steps.length === 0) {
            return;
        }

        const tour = driver({
            showProgress: true,
            animate: true,
            allowClose: true,
            nextBtnText: t('Next'),
            prevBtnText: t('Back'),
            doneBtnText: t('Done'),
            onDestroyed: () => dismiss(),
            steps,
        });

        tour.drive();
    }

    return {
        startAdminShellTour,
        hasDismissed,
        dismiss,
    };
}
