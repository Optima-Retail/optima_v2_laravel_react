import { Link } from '@inertiajs/react';
import { cn } from '@/support/cn';

export type ConfigNavTab = {
    id: string;
    href: string;
    label: string;
};

type ConfigNavTabsProps = {
    tabs: ConfigNavTab[];
    activeId: string;
    className?: string;
    /** Show even when only one tab (useful for hubs that will grow). */
    alwaysShow?: boolean;
};

/** Inertia link tabs — frontend-only section switcher between existing config routes. */
export function ConfigNavTabs({ tabs, activeId, className, alwaysShow = false }: ConfigNavTabsProps) {
    if (tabs.length === 0 || (!alwaysShow && tabs.length <= 1)) {
        return null;
    }

    return (
        <div
            role="tablist"
            aria-orientation="horizontal"
            className={cn(
                'inline-flex max-w-full gap-0.5 overflow-x-auto rounded-xl border border-line bg-canvas p-1',
                '[-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                className,
            )}
        >
            {tabs.map((tab) => {
                const selected = tab.id === activeId;

                return (
                    <Link
                        key={tab.id}
                        href={tab.href}
                        role="tab"
                        aria-selected={selected}
                        tabIndex={selected ? 0 : -1}
                        className={cn(
                            'shrink-0 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition-colors duration-150',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30',
                            selected
                                ? 'bg-brand-soft text-brand shadow-sm'
                                : 'text-ink-muted hover:bg-surface hover:text-ink',
                        )}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </div>
    );
}
