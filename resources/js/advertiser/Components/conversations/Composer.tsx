import { useEffect, useRef, useState, type ChangeEvent, type KeyboardEvent } from 'react';
import { Dropdown, PaperclipIcon, SendIcon, SmileIcon, SparkleIcon, useDismiss, XIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { fileSize } from '@shared/lib/format';
import type { Availability, CannedResponse } from '@shared/types/conversations';

interface Props {
    availability: Availability;
    canned: CannedResponse[];
    disabled: boolean;
    onSend: (body: string, files: File[]) => void;
}

/** Matches the server's own rules — see ConversationController. */
const MAX_FILES = 5;
const MAX_BYTES = 10 * 1024 * 1024;
const ACCEPT = '.pdf,.doc,.docx,.png,.jpg,.jpeg,.zip';

// Enough to react with, few enough to scan. A full picker is a search box over
// three thousand glyphs, which is a different product.
const EMOJI = ['👍', '🙏', '🎉', '✅', '👀', '🔥', '😀', '😅', '🤔', '😐', '😞', '❤️'];

export function Composer({ availability, canned, disabled, onSend }: Props) {
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [error, setError] = useState<string | null>(null);
    const textarea = useRef<HTMLTextAreaElement>(null);
    const picker = useRef<HTMLInputElement>(null);

    // Auto-grow. Height is reset to auto first, or the box can only ever get
    // taller — scrollHeight of an already-tall element is its current height.
    useEffect(() => {
        const element = textarea.current;

        if (element === null) return;

        element.style.height = 'auto';
        element.style.height = `${Math.min(element.scrollHeight, 200)}px`;
    }, [body]);

    const submit = () => {
        const text = body.trim();

        if (disabled || (text === '' && files.length === 0)) return;

        onSend(text, files);
        setBody('');
        setFiles([]);
        setError(null);
        textarea.current?.focus();
    };

    const addFiles = (event: ChangeEvent<HTMLInputElement>) => {
        const picked = [...(event.target.files ?? [])];

        // Reset the input straight away, so picking the same file twice in a
        // row still fires a change event.
        event.target.value = '';

        const tooBig = picked.find((file) => file.size > MAX_BYTES);

        if (tooBig !== undefined) {
            setError(`${tooBig.name} is ${fileSize(tooBig.size)} — the limit is 10 MB per file.`);

            return;
        }

        if (files.length + picked.length > MAX_FILES) {
            setError(`Up to ${MAX_FILES} files per message.`);

            return;
        }

        setError(null);
        setFiles((current) => [...current, ...picked]);
    };

    const insert = (text: string) => {
        setBody((current) => (current.trim() === '' ? text : `${current.trimEnd()} ${text}`));
        textarea.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        // Enter sends, Shift+Enter breaks the line. Not while an IME is
        // composing: in Japanese or Korean, Enter is how you accept a
        // candidate, and sending there posts a half-finished word.
        if (event.key !== 'Enter' || event.shiftKey || event.nativeEvent.isComposing) return;

        event.preventDefault();
        submit();
    };

    return (
        <div className="border-t border-subtle bg-card px-4 py-3 lg:px-6">
            {files.length > 0 && (
                <ul className="mb-2 flex flex-wrap gap-2">
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${index}`}
                            className="flex items-center gap-2 rounded-pill bg-sunken py-1 pl-3 pr-1.5 text-sm text-ink-700"
                        >
                            <span className="max-w-[180px] truncate">{file.name}</span>
                            <span className="num text-xs text-ink-500">{fileSize(file.size)}</span>
                            <button
                                type="button"
                                aria-label={`Remove ${file.name}`}
                                onClick={() => setFiles((current) => current.filter((_, i) => i !== index))}
                                className="flex size-5 items-center justify-center rounded-pill text-ink-500 hover:bg-card hover:text-ink-900"
                            >
                                <XIcon size={12} />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {error !== null && <p className="mb-2 text-sm text-danger">{error}</p>}

            <div
                className={cn(
                    'flex items-end gap-2 rounded-card border border-subtle bg-canvas px-2 py-1.5',
                    'focus-within:border-brand',
                )}
            >
                <textarea
                    ref={textarea}
                    rows={1}
                    value={body}
                    disabled={disabled}
                    onChange={(event) => setBody(event.target.value)}
                    onKeyDown={onKeyDown}
                    aria-label="Write a message"
                    placeholder={disabled ? 'This conversation is closed.' : 'Write a message…'}
                    className="max-h-[200px] min-h-[36px] flex-1 resize-none bg-transparent px-1.5 py-1.5 text-base text-ink-900 outline-none placeholder:text-ink-500 disabled:cursor-not-allowed"
                />

                <div className="flex shrink-0 items-center gap-0.5 pb-0.5">
                    <input
                        ref={picker}
                        type="file"
                        multiple
                        accept={ACCEPT}
                        onChange={addFiles}
                        className="hidden"
                        aria-hidden="true"
                        tabIndex={-1}
                    />

                    <ToolButton
                        label="Attach a file"
                        disabled={disabled}
                        onClick={() => picker.current?.click()}
                        icon={<PaperclipIcon size={16} />}
                    />

                    <EmojiButton disabled={disabled} onPick={(emoji) => insert(emoji)} />

                    <Dropdown
                        align="end"
                        items={canned.map((response) => ({
                            id: response.id,
                            label: response.label,
                            onSelect: () => insert(response.body),
                        }))}
                        trigger={
                            <ToolButton
                                label="Canned responses"
                                disabled={disabled}
                                icon={<SparkleIcon size={16} />}
                            />
                        }
                    />

                    <button
                        type="button"
                        onClick={submit}
                        disabled={disabled || (body.trim() === '' && files.length === 0)}
                        aria-label="Send"
                        className="ml-1 flex size-9 items-center justify-center rounded-button bg-brand text-white transition-colors duration-fast hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <SendIcon size={16} />
                    </button>
                </div>
            </div>

            <p className="mt-2 flex flex-wrap items-start gap-x-3 gap-y-1 text-xs text-ink-500">
                {/* items-start, not items-center: the note wraps to two lines in
                    a narrow pane, and a centred dot then floats between them
                    instead of sitting against the first word. */}
                <span className="flex items-start gap-1.5">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'mt-1 size-1.5 shrink-0 rounded-pill',
                            availability.online ? 'bg-success' : 'bg-ink-300',
                        )}
                    />
                    {availability.note}
                </span>
                <span className="hidden xl:inline">Enter to send, Shift+Enter for a new line.</span>
            </p>
        </div>
    );
}

function ToolButton({
    label,
    icon,
    disabled,
    onClick,
}: {
    label: string;
    icon: React.ReactNode;
    disabled: boolean;
    onClick?: () => void;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className="flex size-9 items-center justify-center rounded-button text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700 disabled:cursor-not-allowed disabled:opacity-40"
        >
            {icon}
        </button>
    );
}

/**
 * A grid, not the Dropdown.
 *
 * Dropdown renders one full-width row per item, which for twelve emoji is a
 * column of twelve mostly-empty rows. The picker is small enough that its own
 * popover is less code than bending the menu into a grid.
 */
function EmojiButton({ disabled, onPick }: { disabled: boolean; onPick: (emoji: string) => void }) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <div ref={ref} className="relative">
            <ToolButton
                label="Emoji"
                disabled={disabled}
                onClick={() => setOpen((current) => !current)}
                icon={<SmileIcon size={16} />}
            />

            {open && (
                <div className="absolute bottom-full right-0 z-50 mb-1 grid w-[200px] animate-scale-in grid-cols-6 gap-0.5 rounded-card border border-subtle bg-card p-1.5 shadow-card">
                    {EMOJI.map((emoji) => (
                        <button
                            key={emoji}
                            type="button"
                            aria-label={emoji}
                            onClick={() => {
                                onPick(emoji);
                                setOpen(false);
                            }}
                            className="flex size-8 items-center justify-center rounded-button text-lg hover:bg-sunken"
                        >
                            {emoji}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
