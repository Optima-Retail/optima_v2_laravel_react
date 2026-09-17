import { useEffect, useRef, useState } from 'react';
import { Eraser, Loader2, PenLine } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { formsService } from '@/services';

type SignaturePadProps = {
    formId: number;
    fieldId: number;
    hasSignature: boolean;
    disabled?: boolean;
    onUploaded: (result: { value: string | null; payload: Record<string, unknown> | null }) => void;
};

export function SignaturePad({ formId, fieldId, hasSignature, disabled, onUploaded }: SignaturePadProps) {
    const { t } = useTranslation();
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawingRef = useRef(false);
    const hasStrokeRef = useRef(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [cacheBust, setCacheBust] = useState(0);

    useEffect(() => {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#1f2937';
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
    }, []);

    function pointFromEvent(canvas: HTMLCanvasElement, event: React.PointerEvent<HTMLCanvasElement>) {
        const rect = canvas.getBoundingClientRect();

        return {
            x: ((event.clientX - rect.left) / rect.width) * canvas.width,
            y: ((event.clientY - rect.top) / rect.height) * canvas.height,
        };
    }

    function startDrawing(event: React.PointerEvent<HTMLCanvasElement>) {
        if (disabled) {
            return;
        }

        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        drawingRef.current = true;
        hasStrokeRef.current = true;
        const point = pointFromEvent(canvas, event);
        context.beginPath();
        context.moveTo(point.x, point.y);
    }

    function draw(event: React.PointerEvent<HTMLCanvasElement>) {
        if (!drawingRef.current) {
            return;
        }

        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        const point = pointFromEvent(canvas, event);
        context.lineTo(point.x, point.y);
        context.stroke();
    }

    function stopDrawing() {
        drawingRef.current = false;
    }

    function clear() {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        hasStrokeRef.current = false;
        setError(null);
    }

    async function save() {
        const canvas = canvasRef.current;

        if (!canvas || !hasStrokeRef.current) {
            setError(t('forms.signatureEmpty'));

            return;
        }

        setSaving(true);
        setError(null);

        canvas.toBlob(async (blob) => {
            if (!blob) {
                setSaving(false);
                setError(t('forms.fieldUploadFailed'));

                return;
            }

            try {
                const file = new File([blob], `signature-${fieldId}.png`, { type: 'image/png' });
                const result = await formsService.uploadFieldFile(formId, fieldId, 'value', file);
                onUploaded(result);
                setCacheBust((value) => value + 1);
                clear();
            } catch {
                setError(t('forms.fieldUploadFailed'));
            } finally {
                setSaving(false);
            }
        }, 'image/png');
    }

    const previewUrl = hasSignature ? `${formsService.fieldFileUrl(formId, fieldId, 'value')}&v=${cacheBust}` : null;

    return (
        <div className="space-y-2">
            {previewUrl ? (
                <img
                    src={previewUrl}
                    alt=""
                    className="h-24 w-auto max-w-full rounded-lg border border-line bg-white object-contain"
                />
            ) : null}

            <canvas
                ref={canvasRef}
                width={480}
                height={160}
                className="w-full touch-none rounded-lg border border-line bg-white"
                style={{ aspectRatio: '3 / 1' }}
                onPointerDown={startDrawing}
                onPointerMove={draw}
                onPointerUp={stopDrawing}
                onPointerLeave={stopDrawing}
            />

            <div className="flex flex-wrap items-center gap-2">
                <Button type="button" variant="secondary" size="sm" disabled={disabled} onClick={clear}>
                    <Eraser className="size-3.5" aria-hidden />
                    {t('forms.signatureClear')}
                </Button>
                <Button type="button" size="sm" disabled={disabled || saving} onClick={save}>
                    {saving ? <Loader2 className="size-3.5 animate-spin" aria-hidden /> : <PenLine className="size-3.5" aria-hidden />}
                    {t('forms.signatureSave')}
                </Button>
            </div>
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}
