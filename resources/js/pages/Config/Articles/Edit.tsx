import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ArticleForm } from '@/components/config/articles/ArticleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { articlesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { ArticleFormData } from '@/support/types/domain/article';

type EditArticleProps = {
    article: ArticleFormData;
    languageOptions: UserOption[];
    clientOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

export default function EditArticle({ article, languageOptions, clientOptions, can }: EditArticleProps) {
    const { t } = useTranslation();
    const form = useForm({
        code: article.code,
        is_deletable: article.is_deletable,
        translations: article.translations,
        clients: article.clients,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        articlesService.update(article.id, form);
    }

    async function destroyArticle() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('articles.resource') }),
            message: t('common.deleteMessage', { name: article.code }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        articlesService.destroy(article.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('articles.resource') })}>
            <Head title={t('common.editItem', { name: article.code })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.articles')}
                    title={t('articles.editTitle')}
                    description={t('common.updateDetails', { name: article.code })}
                    backHref={articlesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('articles.resourcePlural') })}
                />

                <ArticleForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    languageOptions={languageOptions}
                    clientOptions={clientOptions}
                    onChange={(patch) => form.setData((data) => ({ ...data, ...patch }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyArticle}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
