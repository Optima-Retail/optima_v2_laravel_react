import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

type FieldProps = PropsWithChildren<{
    label: string;
    htmlFor: string;
    error?: string;
    className?: string;
    required?: boolean;
}>;

export function Field({ label, htmlFor, error, className, required = false, children }: FieldProps) {
    const { t } = useTranslation();

    return (
        <div className={cn('space-y-1', className)}>
            <label htmlFor={htmlFor} className="mb-1.5 block text-sm font-semibold text-ink">
                {label}
                {required ? (
                    <span className="ml-0.5 text-danger" title={t('common.required')} aria-hidden>
                        *
                    </span>
                ) : null}
                {required ? <span className="sr-only"> ({t('common.required')})</span> : null}
            </label>
            {children}
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}
