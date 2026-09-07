import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { router } from '@inertiajs/react';
import { Avatar, Button, Modal } from '@shared/ui';

interface Props {
    url: string | null;
    name: string;
}

/** Matches the server. A square crop of a phone photo lands well under it. */
const MAX_BYTES = 2 * 1024 * 1024;
const OUTPUT = 512;

/**
 * Picture, cropped square before it is sent.
 *
 * The crop happens in a canvas here rather than on the server, and that is the
 * whole reason the 2MB ceiling is comfortable: what leaves the browser is
 * always a 512px square JPEG of about 60KB, whatever the phone produced. The
 * server's limit is a ceiling on a hostile client, not a size anybody hits.
 */
export function AvatarField({ url, name }: Props) {
    const [picking, setPicking] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    // Object URLs are a leak if a long session opens the picker repeatedly.
    useEffect(() => () => {
        if (picking !== null) URL.revokeObjectURL(picking);
    }, [picking]);

    const choose = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (file === undefined) return;

        if (file.size > MAX_BYTES * 4) {
            // Generous, because the crop shrinks it: this only catches
            // somebody picking a 40MB RAW file.
            setError('That image is too large to open. Try one under 8 MB.');

            return;
        }

        setError(null);
        setPicking(URL.createObjectURL(file));
    };

    return (
        <div className="flex flex-wrap items-center gap-4">
            <Avatar name={name} src={url ?? undefined} size="lg" className="size-16 text-md" />

            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={input}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={choose}
                        className="hidden"
                        tabIndex={-1}
                        aria-hidden="true"
                    />

                    <Button variant="secondary" onClick={() => input.current?.click()}>
                        {url === null ? 'Upload a photo' : 'Change photo'}
                    </Button>

                    {url !== null && (
                        <Button
                            variant="ghost"
                            loading={busy}
                            onClick={() => {
                                setBusy(true);
                                router.delete('/profile/avatar', {
                                    preserveScroll: true,
                                    onFinish: () => setBusy(false),
                                });
                            }}
                        >
                            Remove
                        </Button>
                    )}
                </div>

                <p className="text-sm text-ink-500">Square, PNG or JPEG. We crop and resize it for you.</p>
                {error !== null && <p className="text-sm text-danger">{error}</p>}
            </div>

            {picking !== null && (
                <CropDialog
                    src={picking}
                    onClose={() => {
                        URL.revokeObjectURL(picking);
                        setPicking(null);
                    }}
                />
            )}
        </div>
    );
}

/**
 * Pick the square.
 *
 * Zoom and drag over a fixed square frame, which is the whole interaction
 * anybody needs for an avatar — an arbitrary crop rectangle would let somebody
 * choose a portrait and then be surprised by the circle it renders in.
 */
function CropDialog({ src, onClose }: { src: string; onClose: () => void }) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const image = useRef<HTMLImageElement | null>(null);
    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [ready, setReady] = useState(false);
    const [busy, setBusy] = useState(false);
    const drag = useRef<{ x: number; y: number } | null>(null);

    const FRAME = 260;

    useEffect(() => {
        const img = new Image();

        img.onload = () => {
            image.current = img;
            setReady(true);
        };

        img.src = src;
    }, [src]);

    // Redraw on every change of zoom or offset.
    useEffect(() => {
        const element = canvas.current;
        const img = image.current;

        if (element === null || img === null || !ready) return;

        const context = element.getContext('2d');

        if (context === null) return;

        // The image is scaled so its shorter side fills the frame at zoom 1,
        // which guarantees no transparent gap however it is dragged.
        const base = FRAME / Math.min(img.width, img.height);
        const scale = base * zoom;
        const width = img.width * scale;
        const height = img.height * scale;

        context.clearRect(0, 0, FRAME, FRAME);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, FRAME, FRAME);
        context.drawImage(img, (FRAME - width) / 2 + offset.x, (FRAME - height) / 2 + offset.y, width, height);
    }, [zoom, offset, ready]);

    const save = () => {
        const element = canvas.current;

        if (element === null) return;

        setBusy(true);

        // Re-rendered at output size rather than scaling the preview canvas:
        // upscaling a 260px preview to 512 would ship a blurry avatar.
        const out = document.createElement('canvas');
        out.width = OUTPUT;
        out.height = OUTPUT;

        const context = out.getContext('2d');

        if (context === null) {
            setBusy(false);

            return;
        }

        context.drawImage(element, 0, 0, FRAME, FRAME, 0, 0, OUTPUT, OUTPUT);

        out.toBlob(
            (blob) => {
                if (blob === null) {
                    setBusy(false);

                    return;
                }

                router.post(
                    '/profile/avatar',
                    { avatar: new File([blob], 'avatar.jpg', { type: 'image/jpeg' }) },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: onClose,
                        onFinish: () => setBusy(false),
                    },
                );
            },
            'image/jpeg',
            0.9,
        );
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title="Crop your photo"
            description="Drag to move, and use the slider to zoom."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} loading={busy} disabled={!ready}>
                        Use this photo
                    </Button>
                </>
            }
        >
            <div className="flex flex-col items-center gap-4">
                <canvas
                    ref={canvas}
                    width={FRAME}
                    height={FRAME}
                    aria-label="Crop preview"
                    onPointerDown={(event) => {
                        drag.current = { x: event.clientX - offset.x, y: event.clientY - offset.y };
                        event.currentTarget.setPointerCapture(event.pointerId);
                    }}
                    onPointerMove={(event) => {
                        if (drag.current === null) return;

                        setOffset({ x: event.clientX - drag.current.x, y: event.clientY - drag.current.y });
                    }}
                    onPointerUp={() => {
                        drag.current = null;
                    }}
                    className="cursor-move touch-none rounded-pill border border-subtle bg-sunken"
                />

                <label className="flex w-full items-center gap-3 text-sm text-ink-500">
                    Zoom
                    <input
                        type="range"
                        min={1}
                        max={3}
                        step={0.02}
                        value={zoom}
                        onChange={(event) => setZoom(Number(event.target.value))}
                        className="flex-1 accent-[var(--brand-blue)]"
                    />
                </label>
            </div>
        </Modal>
    );
}
