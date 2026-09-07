import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import type { SharedPageProps } from '@/types';

export function LocaleSync() {
    const { locale } = usePage<SharedPageProps>().props;
    const { i18n } = useTranslation();

    useEffect(() => {
        if (locale && i18n.language !== locale) {
            void i18n.changeLanguage(locale);
        }

        if (locale) {
            document.documentElement.lang = locale;
        }
    }, [i18n, locale]);

    return null;
}
