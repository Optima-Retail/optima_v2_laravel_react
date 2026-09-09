import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle, FontSize } from '@tiptap/extension-text-style';
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    List,
    ListOrdered,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Quote,
    Link as LinkIcon,
    Redo,
    Undo,
    RemoveFormatting,
} from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { cn } from '@/support/cn';
import { isEmptyRichText, normalizeRichText, sanitizeRichText } from '@/support/richText';

type RichTextEditorProps = {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
    minHeightClassName?: string;
};

const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'] as const;

function ToolbarButton({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
            className={cn(
                'inline-flex size-7 items-center justify-center rounded-md text-ink-muted transition',
                'hover:bg-canvas hover:text-ink disabled:pointer-events-none disabled:opacity-40',
                active && 'bg-brand-soft text-brand',
            )}
        >
            {children}
        </button>
    );
}

function ToolbarSelect({
    label,
    value,
    disabled = false,
    onChange,
    children,
    className,
}: {
    label: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
    children: ReactNode;
    className?: string;
}) {
    return (
        <select
            aria-label={label}
            title={label}
            disabled={disabled}
            value={value}
            onMouseDown={(event) => event.stopPropagation()}
            onChange={(event) => onChange(event.target.value)}
            className={cn(
                'h-7 rounded-md border border-line bg-surface px-1.5 text-xs text-ink outline-none',
                'focus:border-brand focus:ring-2 focus:ring-brand/20',
                'disabled:cursor-not-allowed disabled:opacity-40',
                className,
            )}
        >
            {children}
        </select>
    );
}

function ToolbarDivider() {
    return <span className="mx-1 h-4 w-px shrink-0 bg-line" aria-hidden />;
}

export function RichTextEditor({
    id,
    value,
    onChange,
    placeholder,
    invalid = false,
    disabled = false,
    className,
    minHeightClassName = 'min-h-24',
}: RichTextEditorProps) {
    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
                codeBlock: false,
                link: {
                    openOnClick: false,
                    HTMLAttributes: {
                        class: 'text-brand underline',
                        rel: 'noopener noreferrer',
                        target: '_blank',
                    },
                },
            }),
            TextStyle,
            FontSize,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
            Placeholder.configure({
                placeholder: placeholder ?? '',
            }),
        ],
        content: value || '',
        editable: !disabled,
        editorProps: {
            attributes: {
                ...(id ? { id } : {}),
                class: cn(
                    'rich-text-editor prose-sm max-w-none px-3 py-2 text-sm text-ink outline-none',
                    minHeightClassName,
                ),
            },
        },
        onUpdate: ({ editor: current }) => {
            onChange(normalizeRichText(current.getHTML()));
        },
    });

    useEffect(() => {
        if (!editor) {
            return;
        }

        const next = value || '';
        const current = normalizeRichText(editor.getHTML());

        if (normalizeRichText(next) !== current) {
            editor.commands.setContent(next, { emitUpdate: false });
        }
    }, [editor, value]);

    useEffect(() => {
        if (!editor) {
            return;
        }

        editor.setEditable(!disabled);
    }, [disabled, editor]);

    if (!editor) {
        return null;
    }

    const headingLevel = editor.isActive('heading', { level: 1 })
        ? '1'
        : editor.isActive('heading', { level: 2 })
          ? '2'
          : editor.isActive('heading', { level: 3 })
            ? '3'
            : 'paragraph';

    const currentFontSize = (editor.getAttributes('textStyle').fontSize as string | undefined) ?? '';

    function setHeading(value: string) {
        if (!editor) {
            return;
        }

        if (value === 'paragraph') {
            editor.chain().focus().setParagraph().run();
            return;
        }

        const level = Number(value) as 1 | 2 | 3;
        editor.chain().focus().toggleHeading({ level }).run();
    }

    function setFontSize(value: string) {
        if (!editor) {
            return;
        }

        if (!value) {
            editor.chain().focus().unsetFontSize().run();
            return;
        }

        editor.chain().focus().setFontSize(value).run();
    }

    function setLink() {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as string | undefined;
        const url = window.prompt('URL', previous ?? 'https://');

        if (url === null) {
            return;
        }

        const trimmed = url.trim();

        if (trimmed === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: trimmed }).run();
    }

    return (
        <div
            className={cn(
                'overflow-hidden rounded-lg border bg-surface shadow-sm transition',
                'focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20',
                invalid ? 'border-danger focus-within:border-danger focus-within:ring-danger/20' : 'border-line',
                disabled && 'cursor-not-allowed opacity-80',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-0.5 border-b border-line bg-canvas/60 px-1.5 py-1">
                <ToolbarSelect
                    label="Style"
                    value={headingLevel}
                    disabled={disabled}
                    onChange={setHeading}
                    className="min-w-[6.5rem]"
                >
                    <option value="paragraph">Paragraph</option>
                    <option value="1">Heading 1</option>
                    <option value="2">Heading 2</option>
                    <option value="3">Heading 3</option>
                </ToolbarSelect>

                <ToolbarSelect
                    label="Font size"
                    value={currentFontSize}
                    disabled={disabled}
                    onChange={setFontSize}
                    className="min-w-[4.5rem]"
                >
                    <option value="">Size</option>
                    {FONT_SIZES.map((size) => (
                        <option key={size} value={size}>
                            {size.replace('px', '')}
                        </option>
                    ))}
                </ToolbarSelect>

                <ToolbarDivider />

                <ToolbarButton
                    label="Bold"
                    active={editor.isActive('bold')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <Bold className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Italic"
                    active={editor.isActive('italic')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <Italic className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Underline"
                    active={editor.isActive('underline')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleUnderline().run()}
                >
                    <UnderlineIcon className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Strikethrough"
                    active={editor.isActive('strike')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                >
                    <Strikethrough className="size-3.5" aria-hidden />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    label="Align left"
                    active={editor.isActive({ textAlign: 'left' })}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().setTextAlign('left').run()}
                >
                    <AlignLeft className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Align center"
                    active={editor.isActive({ textAlign: 'center' })}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().setTextAlign('center').run()}
                >
                    <AlignCenter className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Align right"
                    active={editor.isActive({ textAlign: 'right' })}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().setTextAlign('right').run()}
                >
                    <AlignRight className="size-3.5" aria-hidden />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    label="Bullet list"
                    active={editor.isActive('bulletList')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                >
                    <List className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Ordered list"
                    active={editor.isActive('orderedList')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                >
                    <ListOrdered className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Quote"
                    active={editor.isActive('blockquote')}
                    disabled={disabled}
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                >
                    <Quote className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Link"
                    active={editor.isActive('link')}
                    disabled={disabled}
                    onClick={setLink}
                >
                    <LinkIcon className="size-3.5" aria-hidden />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    label="Clear formatting"
                    disabled={disabled}
                    onClick={() =>
                        editor.chain().focus().unsetAllMarks().clearNodes().unsetFontSize().run()
                    }
                >
                    <RemoveFormatting className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Undo"
                    disabled={disabled || !editor.can().undo()}
                    onClick={() => editor.chain().focus().undo().run()}
                >
                    <Undo className="size-3.5" aria-hidden />
                </ToolbarButton>
                <ToolbarButton
                    label="Redo"
                    disabled={disabled || !editor.can().redo()}
                    onClick={() => editor.chain().focus().redo().run()}
                >
                    <Redo className="size-3.5" aria-hidden />
                </ToolbarButton>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}

type RichTextHtmlProps = {
    html: string | null | undefined;
    className?: string;
};

export function RichTextHtml({ html, className }: RichTextHtmlProps) {
    if (isEmptyRichText(html)) {
        return null;
    }

    return (
        <div
            className={cn('rich-text-content prose-sm max-w-none break-words text-sm text-ink', className)}
            dangerouslySetInnerHTML={{ __html: sanitizeRichText(html) }}
        />
    );
}
