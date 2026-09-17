import { cn } from '@/support/cn';

type UserAvatarProps = {
    name: string;
    avatarUrl?: string | null;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
};

const sizeClass = {
    sm: 'size-7 text-[0.65rem]',
    md: 'size-8 text-xs',
    lg: 'size-16 text-lg',
} as const;

function initialsFromName(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[parts.length - 1][0] ?? ''}`.toUpperCase();
}

export function UserAvatar({ name, avatarUrl = null, size = 'md', className }: UserAvatarProps) {
    if (avatarUrl) {
        return (
            <img
                src={avatarUrl}
                alt=""
                title={name}
                className={cn(sizeClass[size], 'shrink-0 rounded-full object-cover', className)}
            />
        );
    }

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand',
                sizeClass[size],
                className,
            )}
            aria-hidden
        >
            {initialsFromName(name)}
        </span>
    );
}
