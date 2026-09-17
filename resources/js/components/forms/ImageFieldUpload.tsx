import { useRef, useState } from 'react';
import { ImageUp, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { formsService } from '@/services';

type ImageFieldUploadProps = {
    formId: number;
    fieldId: number;
    target: 'value' | 'before' | 'after';
    hasFile: boolean;
    disabled?: boolean;
    onUploaded: (result: { value: string | null; payload: Record<string, unknown> | null }) => void;
};

export function ImageFieldUpload({ formId, fieldId, target, hasFile, disabled, onUploaded }: ImageFieldUploadProps) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    // Bust the browser cache after a replace so the same field's <img> refetches.
    const [cacheBust, setCacheBust] = useState(0);

    async function handleFile(file: File | null | undefined) {
        if (!file || disabled) {
            return;
        }

        setUploading(true);
        setError(null);

        try {
            const result = await formsService.uploadFieldFile(formId, fieldId, target, file);
            onUploaded(result);
            setCacheBust((value) => value + 1);
        } catch {
            setError(t('forms.fieldUploadFailed'));
        } finally {
            setUploading(false);

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    }

    const previewUrl = hasFile ? `${formsService.fieldFileUrl(formId, fieldId, target)}&v=${cacheBust}` : null;

    return (
        <div className="space-y-2">
            {previewUrl ? (
                <img
                    src={previewUrl}
                    alt=""
                    className="max-h-48 w-auto max-w-full rounded-lg border border-line object-contain"
                />
            ) : null}

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(event) => handleFile(event.target.files?.[0])}
            />
            <Button
                type="button"
                variant="secondary"
                disabled={disabled || uploading}
                onClick={() => inputRef.current?.click()}
            >
                {uploading ? <Loader2 className="size-3.5 animate-spin" aria-hidden /> : <ImageUp className="size-3.5" aria-hidden />}
                {hasFile ? t('forms.replaceImage') : t('forms.uploadImage')}
            </Button>
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}
