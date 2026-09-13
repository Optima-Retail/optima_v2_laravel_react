import type { PropsWithChildren } from 'react';
import { usePage } from '@inertiajs/react';
import { ConfirmModal } from '@/components/feedback/ConfirmModal';
import { ToastHost } from '@/components/feedback/ToastHost';
import { FieldHelpProvider } from '@/components/field-help/FieldHelpProvider';
import { LocaleSync } from '@/components/i18n/LocaleSync';
import { ConfigSidebar, isConfigRoute } from '@/components/navigation/ConfigSidebar';
import { Sidebar } from '@/components/navigation/Sidebar';
import { Topbar } from '@/components/navigation/Topbar';
import { breadcrumbsFromUrl, type BreadcrumbItem } from '@/helpers/breadcrumb';
import type { SharedPageProps } from '@/types';

type AppLayoutProps = PropsWithChildren<{
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
}>;

export function AppLayout({ children, title, breadcrumbs }: AppLayoutProps) {
    const page = usePage<SharedPageProps>();
    const crumbs = breadcrumbs ?? breadcrumbsFromUrl(page.url, title);
    const showConfigSidebar = isConfigRoute(page.url);
    const companyKey = page.props.auth.company?.id ?? 'none';

    return (
        <FieldHelpProvider>
            <div className="flex h-screen overflow-hidden bg-canvas">
                <Sidebar />
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <Topbar title={title} user={page.props.auth.user} breadcrumbs={crumbs} />
                    <main className="flex min-h-0 flex-1 overflow-hidden">
                        {showConfigSidebar ? <ConfigSidebar /> : null}
                        <div className="app-scroll min-w-0 flex-1 overflow-y-auto px-3 py-4 sm:px-4 lg:px-6">
                            <div key={companyKey} className="w-full">
                                {children}
                            </div>
                        </div>
                    </main>
                </div>
                <LocaleSync />
                <ToastHost />
                <ConfirmModal />
            </div>
        </FieldHelpProvider>
    );
}
