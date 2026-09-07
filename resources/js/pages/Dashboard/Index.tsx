import { Head } from '@inertiajs/react';
import { KeyRound, Shield } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { useAuthUser } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';

type DashboardProps = {
    stats: {
        roles: string[];
        permissions: string[];
    };
};

export default function Dashboard({ stats }: DashboardProps) {
    const { t } = useTranslation();
    const user = useAuthUser();

    const cards = [
        { key: 'roles' as const, label: t('dashboard.roles'), icon: Shield },
        { key: 'permissions' as const, label: t('dashboard.permissions'), icon: KeyRound },
    ];

    const values = {
        roles: stats.roles.join(', ') || t('common.emDash'),
        permissions: String(stats.permissions.length),
    };

    return (
        <AppLayout title={t('dashboard.overview')}>
            <Head title={t('dashboard.title')} />
            <div className="space-y-6">
                <PageHeader
                    eyebrow={t('dashboard.hello')}
                    title={t('dashboard.ready', { name: user?.name })}
                    description={t('dashboard.description')}
                />

                <section className="grid gap-4 sm:grid-cols-2">
                    {cards.map(({ key, label, icon: Icon }) => (
                        <article key={key} className="rounded-2xl border border-line bg-surface p-5">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-muted">
                                    {label}
                                </p>
                                <span className="flex size-9 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                    <Icon className="size-4" aria-hidden />
                                </span>
                            </div>
                            <p className="mt-3 font-display text-xl font-semibold text-ink">{values[key]}</p>
                        </article>
                    ))}
                </section>
            </div>
        </AppLayout>
    );
}
