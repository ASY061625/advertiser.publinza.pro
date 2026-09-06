import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { DownloadIcon, XIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { fileSize } from '@shared/lib/format';
import type { MessageAttachment } from '@shared/types/conversations';

/**
 * What came with a message.
 *
 * Images are the content — a screenshot of a broken link is the whole message —
 * so they render as thumbnails you can open. Everything else is a file somebody
 * needs to get out of the browser, so it renders as a chip with its size and a
 * download.
 */
export function Attachments({ files, mine }: { files: MessageAttachment[]; mine: boolean }) {
    const [lightbox, setLightbox] = useState<MessageAttachment | null>(null);

    if (files.length === 0) return null;

    const images = files.filter((file) => file.isImage);
    const documents = files.filter((file) => !file.isImage);

    return (
        <>
            {images.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-2">
                    {images.map((file) => (
                        <li key={file.id}>
                            <button
                                type="button"
                                onClick={() => setLightbox(file)}
                                className="block overflow-hidden rounded-card border border-subtle transition-opacity duration-fast hover:opacity-90"
                            >
                                <img
                                    src={file.url}
                                    alt={file.name}
                                    loading="lazy"
                                    className="h-28 w-auto max-w-[220px] object-cover"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {documents.length > 0 && (
                <ul className="mt-2 flex flex-col gap-1.5">
                    {documents.map((file) => (
                        <li key={file.id}>
                            <a
                                href={file.url}
                                className={cn(
                                    'flex items-center gap-2 rounded-card border px-2.5 py-1.5 text-sm transition-colors duration-fast',
                                    mine
                                        ? 'border-brand/20 bg-card hover:bg-sunken'
                                        : 'border-subtle bg-sunken hover:bg-canvas',
                                )}
                            >
                                <FileGlyph name={file.name} />

                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-ink-900">{file.name}</span>
                                    <span className="num block text-xs text-ink-500">{fileSize(file.sizeBytes)}</span>
                                </span>

                                <DownloadIcon size={14} className="shrink-0 text-ink-500" />
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            {lightbox !== null && <Lightbox file={lightbox} onClose={() => setLightbox(null)} />}
        </>
    );
}

/** The extension, in a box. Cheaper than an icon set and says more. */
function FileGlyph({ name }: { name: string }) {
    const extension = name.includes('.') ? name.split('.').pop()!.slice(0, 4).toUpperCase() : 'FILE';

    return (
        <span className="num flex size-8 shrink-0 items-center justify-center rounded-[4px] bg-brand-subtle text-[10px] font-medium text-brand">
            {extension}
        </span>
    );
}

/**
 * The image, full size, over everything.
 *
 * Portalled to the body: inside the message list it would be clipped by the
 * scroller it lives in, which is the whole reason a lightbox exists.
 */
function Lightbox({ file, onClose }: { file: MessageAttachment; onClose: () => void }) {
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label={file.name}
            onClick={onClose}
            className="fixed inset-0 z-[60] flex items-center justify-center bg-overlay p-6"
        >
            <img
                src={file.url}
                alt={file.name}
                onClick={(event) => event.stopPropagation()}
                className="max-h-full max-w-full rounded-card object-contain"
            />

            <div className="absolute right-4 top-4 flex items-center gap-2">
                <a
                    href={file.url}
                    onClick={(event) => event.stopPropagation()}
                    className="flex items-center gap-1.5 rounded-button bg-card px-3 py-2 text-sm text-ink-900 hover:bg-sunken"
                >
                    <DownloadIcon size={14} />
                    Download
                </a>

                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close"
                    className="flex size-9 items-center justify-center rounded-button bg-card text-ink-700 hover:bg-sunken"
                >
                    <XIcon size={16} />
                </button>
            </div>
        </div>,
        document.body,
    );
}
