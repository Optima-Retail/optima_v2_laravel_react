/** Client app that created or last touched a form (legacy `plataformas`). */
export const AppPlatform = {
    Web: 1,
    Android: 2,
    Ios: 3,
} as const;

export type AppPlatformId = (typeof AppPlatform)[keyof typeof AppPlatform];

export type AppPlatformOption = {
    id: AppPlatformId;
    label: string;
};

export const APP_PLATFORM_OPTIONS: AppPlatformOption[] = [
    { id: AppPlatform.Web, label: 'Web' },
    { id: AppPlatform.Android, label: 'Android' },
    { id: AppPlatform.Ios, label: 'iOS' },
];
