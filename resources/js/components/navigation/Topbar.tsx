import { PanelLeftClose, PanelLeftOpen, LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Breadcrumb } from '@/components/navigation/Breadcrumb';
import { CompanySwitcher } from '@/components/navigation/CompanySwitcher';
import { LocaleSwitcher } from '@/components/navigation/LocaleSwitcher';
import { authService } from '@/services';
import { useUiStore } from '@/stores/uiStore';
import { cn } from '@/support/cn';
import { SHELL_HEADER_CLASS } from '@/support/shell';
import type { BreadcrumbItem } from '@/helpers/breadcrumb';
import type { AuthUser } from '@/types';

type TopbarProps = {
    title?: string;
    user: AuthUser | null;
    breadcrumbs: BreadcrumbItem[];
};

export function Topbar({ user, breadcrumbs }: TopbarProps) {
    const { t } = useTranslation();
    const sidebarOpen = useUiStore((state) => state.sidebarOpen);
    const toggleSidebar = useUiStore((state) => state.toggleSidebar);

    return (
        <header className={cn(SHELL_HEADER_CLASS, 'sticky top-0 z-30 justify-between gap-3 px-3 sm:px-4 lg:px-6')}>
            <div className="flex min-w-0 items-center gap-2">
                <button
                    type="button"
                    onClick={toggleSidebar}
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                    aria-label={sidebarOpen ? t('nav.collapseSidebar') : t('nav.expandSidebar')}
                >
                    {sidebarOpen ? (
                        <PanelLeftClose className="size-4" aria-hidden />
                    ) : (
                        <PanelLeftOpen className="size-4" aria-hidden />
                    )}
                </button>
                <Breadcrumb items={breadcrumbs} className="min-w-0 truncate" />
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <CompanySwitcher />
                <LocaleSwitcher compact />
                <span className="hidden text-sm text-ink-muted sm:inline">{user?.name}</span>
                <button
                    type="button"
                    onClick={() => authService.logout()}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 text-sm font-semibold text-ink transition-colors hover:border-brand/40 hover:text-brand"
                >
                    <LogOut className="size-3.5" aria-hidden />
                    <span className="hidden sm:inline">{t('nav.signOut')}</span>
                </button>
            </div>
        </header>
    );
}
