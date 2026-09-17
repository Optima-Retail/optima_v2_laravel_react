import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { UserAvatar } from '@/components/navigation/UserAvatar';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { TabPanel, Tabs } from '@/components/ui/Tabs';
import { AppLayout } from '@/layouts/AppLayout';
import { profileService } from '@/services';
import { cn } from '@/support/cn';

type ProfileData = {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    username: string | null;
    phone: string | null;
    locale: string | null;
    is_active: boolean;
    roles: string[];
    timezone: string | null;
    team: string | null;
    brand: string | null;
};

type ProfilePageProps = {
    profile: ProfileData;
};

function ReadOnlyValue({ value, empty = '—' }: { value: string | null | undefined; empty?: string }) {
    const display = value?.trim() ? value : empty;

    return (
        <p
            className={cn(
                'flex h-8 items-center rounded-lg border border-line bg-canvas px-3 text-sm',
                value?.trim() ? 'text-ink' : 'text-ink-muted',
            )}
        >
            {display}
        </p>
    );
}

export default function ProfileIndex({ profile }: ProfilePageProps) {
    const { t } = useTranslation();
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submitPassword(event: FormEvent) {
        event.preventDefault();
        profileService.updatePassword(passwordForm, {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset('current_password', 'password', 'password_confirmation');
            },
        });
    }

    const localeLabel = profile.locale
        ? t(`locale.${profile.locale}`, { defaultValue: profile.locale })
        : null;

    return (
        <AppLayout title={t('profile.title')}>
            <Head title={t('profile.title')} />
            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader
                    title={t('profile.title')}
                    description={t('profile.description')}
                />

                <div className="flex items-center gap-4 rounded-xl border border-line bg-surface p-4">
                    <UserAvatar name={profile.name} avatarUrl={profile.avatar_url} size="lg" />
                    <div className="min-w-0">
                        <p className="truncate text-lg font-semibold text-ink">{profile.name}</p>
                        <p className="truncate text-sm text-ink-muted">{profile.email}</p>
                    </div>
                </div>

                <Tabs
                    items={[
                        { id: 'details', label: t('profile.tabs.details') },
                        { id: 'password', label: t('profile.tabs.password') },
                    ]}
                    defaultValue="details"
                >
                    <TabPanel id="details">
                        <div className="grid gap-4 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2">
                            <Field label={t('common.name')} htmlFor="profile-name" helpField={false}>
                                <ReadOnlyValue value={profile.name} />
                            </Field>
                            <Field label={t('common.email')} htmlFor="profile-email" helpField={false}>
                                <ReadOnlyValue value={profile.email} />
                            </Field>
                            <Field label={t('users.username')} htmlFor="profile-username" helpField={false}>
                                <ReadOnlyValue value={profile.username} />
                            </Field>
                            <Field label={t('users.phone')} htmlFor="profile-phone" helpField={false}>
                                <ReadOnlyValue value={profile.phone} />
                            </Field>
                            <Field label={t('users.locale')} htmlFor="profile-locale" helpField={false}>
                                <ReadOnlyValue value={localeLabel} />
                            </Field>
                            <Field label={t('common.status')} htmlFor="profile-status" helpField={false}>
                                <ReadOnlyValue
                                    value={profile.is_active ? t('common.active') : t('common.inactive')}
                                />
                            </Field>
                            <Field label={t('common.roles')} htmlFor="profile-roles" helpField={false}>
                                <ReadOnlyValue
                                    value={profile.roles.length > 0 ? profile.roles.join(', ') : null}
                                />
                            </Field>
                            <Field label={t('users.timezone')} htmlFor="profile-timezone" helpField={false}>
                                <ReadOnlyValue value={profile.timezone} />
                            </Field>
                            <Field label={t('users.team')} htmlFor="profile-team" helpField={false}>
                                <ReadOnlyValue value={profile.team} />
                            </Field>
                            <Field label={t('users.brand')} htmlFor="profile-brand" helpField={false}>
                                <ReadOnlyValue value={profile.brand} />
                            </Field>
                        </div>
                    </TabPanel>

                    <TabPanel id="password">
                        <form
                            onSubmit={submitPassword}
                            className="max-w-md space-y-4 rounded-xl border border-line bg-surface p-4"
                        >
                            <Field
                                label={t('profile.currentPassword')}
                                htmlFor="current_password"
                                required
                                error={passwordForm.errors.current_password}
                                helpField={false}
                            >
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={passwordForm.data.current_password}
                                    invalid={Boolean(passwordForm.errors.current_password)}
                                    onChange={(event) =>
                                        passwordForm.setData('current_password', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label={t('users.newPassword')}
                                htmlFor="password"
                                required
                                error={passwordForm.errors.password}
                                helpField={false}
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwordForm.data.password}
                                    invalid={Boolean(passwordForm.errors.password)}
                                    onChange={(event) => passwordForm.setData('password', event.target.value)}
                                />
                            </Field>
                            <Field
                                label={t('users.confirmPassword')}
                                htmlFor="password_confirmation"
                                required
                                error={passwordForm.errors.password_confirmation}
                                helpField={false}
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwordForm.data.password_confirmation}
                                    invalid={Boolean(passwordForm.errors.password_confirmation)}
                                    onChange={(event) =>
                                        passwordForm.setData('password_confirmation', event.target.value)
                                    }
                                />
                            </Field>
                            <div className="pt-1">
                                <Button type="submit" loading={passwordForm.processing}>
                                    {t('profile.updatePassword')}
                                </Button>
                            </div>
                        </form>
                    </TabPanel>
                </Tabs>
            </div>
        </AppLayout>
    );
}
