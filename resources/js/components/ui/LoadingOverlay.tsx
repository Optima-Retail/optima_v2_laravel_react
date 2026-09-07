import type { PropsWithChildren } from 'react';
import { Spinner } from '@/components/ui/Spinner';
import { cn } from '@/support/cn';

type LoadingOverlayProps = PropsWithChildren<{
    show: boolean;
    className?: string;
    overlayClassName?: string;
    label?: string;
}>;

/**
 * Light transparent loading overlay with a spinner.
 * Wrap any relative container (tables, cards, panels); toggle with `show`.
 */
export function LoadingOverlay({
    show,
    children,
    className,
    overlayClassName,
    label = 'Loading',
}: LoadingOverlayProps) {
    return (
        <div className={cn('relative', className)}>
            {children}
            {show ? (
                <div
                    className={cn(
                        'absolute inset-0 z-10 flex items-center justify-center',
                        'bg-surface/45 backdrop-blur-[1px]',
                        overlayClassName,
                    )}
                    aria-busy="true"
                    aria-live="polite"
                >
                    <Spinner size="md" label={label} />
                </div>
            ) : null}
        </div>
    );
}
