import {
    createContext,
    useCallback,
    useContext,
    useId,
    useMemo,
    useState,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { cn } from '@/support/cn';

type TabsContextValue = {
    activeId: string;
    setActiveId: (id: string) => void;
    baseId: string;
};

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabsContext(component: string): TabsContextValue {
    const context = useContext(TabsContext);

    if (!context) {
        throw new Error(`${component} must be used within <Tabs>`);
    }

    return context;
}

export type TabItem = {
    id: string;
    label: string;
    disabled?: boolean;
};

type TabsProps = {
    items: TabItem[];
    defaultValue?: string;
    value?: string;
    onValueChange?: (id: string) => void;
    children: ReactNode;
    className?: string;
    listClassName?: string;
};

export function Tabs({
    items,
    defaultValue,
    value,
    onValueChange,
    children,
    className,
    listClassName,
}: TabsProps) {
    const baseId = useId();
    const firstEnabled = items.find((item) => !item.disabled)?.id ?? items[0]?.id ?? '';
    const [uncontrolled, setUncontrolled] = useState(defaultValue ?? firstEnabled);
    const activeId = value ?? uncontrolled;

    const setActiveId = useCallback(
        (id: string) => {
            if (value === undefined) {
                setUncontrolled(id);
            }

            onValueChange?.(id);
        },
        [onValueChange, value],
    );

    const contextValue = useMemo(
        () => ({
            activeId,
            setActiveId,
            baseId,
        }),
        [activeId, baseId, setActiveId],
    );

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const enabled = items.filter((item) => !item.disabled);
        const currentIndex = enabled.findIndex((item) => item.id === activeId);

        if (currentIndex < 0 || enabled.length === 0) {
            return;
        }

        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            nextIndex = (currentIndex + 1) % enabled.length;
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            nextIndex = (currentIndex - 1 + enabled.length) % enabled.length;
        } else if (event.key === 'Home') {
            event.preventDefault();
            nextIndex = 0;
        } else if (event.key === 'End') {
            event.preventDefault();
            nextIndex = enabled.length - 1;
        } else {
            return;
        }

        setActiveId(enabled[nextIndex].id);
    }

    return (
        <TabsContext.Provider value={contextValue}>
            <div className={cn('space-y-5', className)}>
                <div
                    role="tablist"
                    aria-orientation="horizontal"
                    onKeyDown={onKeyDown}
                    className={cn(
                        'inline-flex max-w-full gap-0.5 overflow-x-auto rounded-xl border border-line bg-canvas p-1',
                        '[-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                        listClassName,
                    )}
                >
                    {items.map((item) => {
                        const selected = item.id === activeId;
                        const tabId = `${baseId}-tab-${item.id}`;
                        const panelId = `${baseId}-panel-${item.id}`;

                        return (
                            <button
                                key={item.id}
                                id={tabId}
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                aria-controls={panelId}
                                tabIndex={selected ? 0 : -1}
                                disabled={item.disabled}
                                onClick={() => setActiveId(item.id)}
                                className={cn(
                                    'shrink-0 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition-colors duration-150',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30',
                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                    selected
                                        ? 'bg-brand-soft text-brand shadow-sm'
                                        : 'text-ink-muted hover:bg-surface hover:text-ink',
                                )}
                            >
                                {item.label}
                            </button>
                        );
                    })}
                </div>
                {children}
            </div>
        </TabsContext.Provider>
    );
}

type TabPanelProps = {
    id: string;
    children: ReactNode;
    className?: string;
    forceMount?: boolean;
};

export function TabPanel({ id, children, className, forceMount = false }: TabPanelProps) {
    const { activeId, baseId } = useTabsContext('TabPanel');
    const selected = activeId === id;

    if (!forceMount && !selected) {
        return null;
    }

    return (
        <div
            id={`${baseId}-panel-${id}`}
            role="tabpanel"
            aria-labelledby={`${baseId}-tab-${id}`}
            hidden={!selected}
            className={cn(selected ? 'space-y-5' : 'hidden', className)}
        >
            {children}
        </div>
    );
}
