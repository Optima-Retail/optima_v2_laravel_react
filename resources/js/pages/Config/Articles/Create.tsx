import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ArticleForm } from '@/components/config/articles/ArticleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { articlesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateArticleProps = {
    languageOptions: UserOption[];
    clientOptions: UserOption[];
};

export default function CreateArticle({ languageOptions, clientOptions }: CreateArticleProps) {
    const { t } = useTranslation();
    const form = useForm({
        code: '',
        is_deletable: true,
        translations: languageOptions[0]
            ? [{ language_id: languageOptions[0].id, name: '', description: '' }]
            : [],
        clients: [] as Array<{ company_relationship_id: number | string; sale_price: string }>,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        articlesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('articles.resource') })}>
            <Head title={t('common.newItem', { resource: t('articles.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.articles')}
                    title={t('articles.createTitle')}
                    description={t('articles.createDescription')}
                    backHref={articlesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('articles.resourcePlural') })}
                />

                <ArticleForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    languageOptions={languageOptions}
                    clientOptions={clientOptions}
                    onChange={(patch) => form.setData((data) => ({ ...data, ...patch }))}
                    onSubmit={submit}
                    submitLabel={t('articles.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
