import type { PropsWithChildren } from 'react';
import { Link } from '@inertiajs/react';
import { ToastHost } from '@/components/feedback/ToastHost';
import { LocaleSync } from '@/components/i18n/LocaleSync';
import { LocaleSwitcher } from '@/components/navigation/LocaleSwitcher';

export function AuthLayout({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
            <div className="absolute right-4 top-4">
                <LocaleSwitcher />
            </div>
            <div className="w-full max-w-md">
                <Link href="/" className="mb-8 flex items-center justify-center gap-2">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand font-display text-lg font-bold text-white">
                        O
                    </span>
                    <span className="font-display text-xl font-semibold tracking-tight text-ink">Optima</span>
                </Link>
                {children}
            </div>
            <LocaleSync />
            <ToastHost />
        </div>
    );
}
