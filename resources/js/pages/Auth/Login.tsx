import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Toggle } from '@/components/ui/Toggle';
import { AuthLayout } from '@/layouts/AuthLayout';
import { authService } from '@/services';

export default function Login() {
    const { t } = useTranslation();
    const form = useForm({
        email: 'admin@optima.test',
        password: 'password',
        remember: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        authService.login(form);
    }

    return (
        <AuthLayout>
            <Head title={t('auth.signIn')} />
            <div className="rounded-3xl border border-line bg-surface p-6 sm:p-8">
                <div className="mb-6 space-y-1">
                    <h1 className="font-display text-2xl font-semibold text-ink">{t('auth.welcome')}</h1>
                    <p className="text-sm text-ink-muted">{t('auth.subtitle')}</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('auth.email')} htmlFor="email" error={form.errors.email} required>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            autoComplete="username"
                            invalid={Boolean(form.errors.email)}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </Field>

                    <Field label={t('auth.password')} htmlFor="password" error={form.errors.password} required>
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            autoComplete="current-password"
                            invalid={Boolean(form.errors.password)}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </Field>

                    <Toggle
                        checked={form.data.remember}
                        onCheckedChange={(checked) => form.setData('remember', checked)}
                        checkedLabel={t('auth.rememberMe')}
                        uncheckedLabel={t('auth.rememberMe')}
                        aria-label={t('auth.rememberMe')}
                    />

                    <Button type="submit" className="w-full" loading={form.processing}>
                        <LogIn className="size-4" aria-hidden />
                        {t('auth.signIn')}
                    </Button>
                </form>
            </div>
        </AuthLayout>
    );
}
