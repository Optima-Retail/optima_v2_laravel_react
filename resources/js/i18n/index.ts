import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { en } from '@/i18n/locales/en';
import { es } from '@/i18n/locales/es';

export const supportedLanguages = ['en', 'es'] as const;

export type AppLanguage = (typeof supportedLanguages)[number];

void i18n.use(initReactI18next).init({
    resources: {
        en: { translation: en },
        es: { translation: es },
    },
    lng: 'en',
    fallbackLng: 'en',
    supportedLngs: [...supportedLanguages],
    interpolation: {
        escapeValue: false,
    },
});

export default i18n;
