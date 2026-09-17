import { useEffect, useId, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, LogOut, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { UserAvatar } from '@/components/navigation/UserAvatar';
import { authService, profileService } from '@/services';
import { cn } from '@/support/cn';
import type { AuthUser } from '@/types';

type UserMenuProps = {
    user: AuthUser;
};

export function UserMenu({ user }: UserMenuProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);
    const menuId = useId();

    useEffect(() => {
        function onPointerDown(event: MouseEvent) {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                aria-label={t('profile.menuLabel')}
                onClick={() => setOpen((currentOpen) => !currentOpen)}
                className="inline-flex h-8 max-w-52 items-center gap-1.5 rounded-lg border border-line bg-surface py-0.5 pl-0.5 pr-2 text-sm font-semibold text-ink transition-colors hover:border-brand/40 hover:text-brand"
            >
                <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="sm" />
                <span className="hidden min-w-0 truncate sm:inline">{user.name}</span>
                <ChevronDown
                    className={cn('size-3.5 shrink-0 text-ink-muted transition-transform', open && 'rotate-180')}
                    aria-hidden
                />
            </button>

            {open ? (
                <ul
                    id={menuId}
                    role="menu"
                    aria-label={t('profile.menuLabel')}
                    className="absolute right-0 top-full z-50 mt-1 min-w-44 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-lg"
                >
                    <li role="none">
                        <Link
                            href={profileService.showPath}
                            role="menuitem"
                            onClick={() => setOpen(false)}
                            className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-ink transition-colors hover:bg-canvas"
                        >
                            <UserRound className="size-3.5 shrink-0 text-ink-muted" aria-hidden />
                            <span>{t('profile.title')}</span>
                        </Link>
                    </li>
                    <li role="none">
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => {
                                setOpen(false);
                                authService.logout();
                            }}
                            className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-ink transition-colors hover:bg-canvas"
                        >
                            <LogOut className="size-3.5 shrink-0 text-ink-muted" aria-hidden />
                            <span>{t('nav.signOut')}</span>
                        </button>
                    </li>
                </ul>
            ) : null}
        </div>
    );
}
