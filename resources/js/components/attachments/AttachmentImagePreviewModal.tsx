import { BaseModal } from '@/components/ui/BaseModal';

type AttachmentImagePreviewModalProps = {
    open: boolean;
    title: string;
    src: string | null;
    alt?: string;
    onClose: () => void;
};

export function AttachmentImagePreviewModal({
    open,
    title,
    src,
    alt,
    onClose,
}: AttachmentImagePreviewModalProps) {
    return (
        <BaseModal open={open} title={title} onClose={onClose} size="xl">
            {src ? (
                <div className="flex justify-center">
                    <img
                        src={src}
                        alt={alt ?? title}
                        className="max-h-[70vh] w-auto max-w-full rounded-lg object-contain"
                    />
                </div>
            ) : null}
        </BaseModal>
    );
}
