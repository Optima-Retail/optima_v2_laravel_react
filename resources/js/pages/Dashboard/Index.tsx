import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/AppLayout';

export default function Dashboard() {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t('dashboard.title')} />
        </AppLayout>
    );
}
