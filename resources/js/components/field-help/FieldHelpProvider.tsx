import { useEffect, type PropsWithChildren } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import type { SharedPageProps } from '@/types';
import { useFieldHelpStore } from '@/stores/fieldHelpStore';

/**
 * Keeps the field-help store aligned with the active app locale.
 * Mount once under authenticated layouts.
 */
export function FieldHelpProvider({ children }: PropsWithChildren) {
    const pageLocale = usePage<SharedPageProps>().props.locale;
    const { i18n } = useTranslation();
    const setLocale = useFieldHelpStore((state) => state.setLocale);

    useEffect(() => {
        const locale = pageLocale || i18n.language || 'en';
        setLocale(locale);
    }, [i18n.language, pageLocale, setLocale]);

    return children;
}
