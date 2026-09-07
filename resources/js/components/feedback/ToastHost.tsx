import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Toast } from '@/components/feedback/Toast';
import { useToastStore } from '@/stores/toastStore';
import type { SharedPageProps } from '@/types';

export function ToastHost() {
    const { flash } = usePage<SharedPageProps>().props;
    const { t } = useTranslation();
    const push = useToastStore((state) => state.push);
    const lastKey = useRef<string | null>(null);

    useEffect(() => {
        const message = flash.success || flash.error;
        if (!message) {
            lastKey.current = null;
            return;
        }

        const key = `${flash.success ?? ''}|${flash.error ?? ''}`;
        if (lastKey.current === key) {
            return;
        }

        lastKey.current = key;
        const translated = t(`messages.${message}`, { defaultValue: message });
        push(translated, flash.error ? 'error' : 'success');
    }, [flash.error, flash.success, push, t]);

    return <Toast />;
}
