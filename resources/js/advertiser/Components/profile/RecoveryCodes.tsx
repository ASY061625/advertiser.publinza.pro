import { useState } from 'react';
import { Alert, Button, CheckIcon, CopyIcon, DownloadIcon, Modal } from '@shared/ui';

/**
 * The eight codes, shown once.
 *
 * The confirmation is a real gate rather than a courtesy: these are the only
 * way back into an account whose authenticator is on a lost phone, they are
 * hashed the moment they are generated, and this dialog is the one and only
 * time they exist in readable form. Letting somebody close it with an X is
 * letting them lock themselves out later.
 */
export function RecoveryCodes({ codes, onDone }: { codes: string[]; onDone: () => void }) {
    const [confirmed, setConfirmed] = useState(false);
    const [copied, setCopied] = useState(false);

    const text = codes.join('\n');

    return (
        <Modal
            open
            // No dismiss. The only way out is the confirmation below.
            onClose={() => undefined}
            size="md"
            title="Save your recovery codes"
            description="Each one signs you in once if you lose your authenticator."
            footer={
                <Button disabled={!confirmed} onClick={onDone}>
                    I've saved these
                </Button>
            }
        >
            <div className="flex flex-col gap-4">
                <Alert tone="warning" title="This is the only time you will see these">
                    We store them hashed, so we cannot show them again. If you lose both these codes and your
                    authenticator, getting back in means proving who you are to support.
                </Alert>

                <ul className="num grid grid-cols-2 gap-2 rounded-card bg-sunken p-4 text-base text-ink-900">
                    {codes.map((code) => (
                        <li key={code} className="tracking-wide">
                            {code}
                        </li>
                    ))}
                </ul>

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        onClick={() => {
                            void navigator.clipboard
                                ?.writeText(text)
                                .then(() => setCopied(true))
                                .catch(() => undefined);
                        }}
                    >
                        {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
                        {copied ? 'Copied' : 'Copy'}
                    </Button>

                    <Button variant="secondary" onClick={() => download('publinza-recovery-codes.txt', text)}>
                        <DownloadIcon size={14} />
                        Download
                    </Button>
                </div>

                <label className="flex cursor-pointer items-start gap-2.5 text-base text-ink-700">
                    <input
                        type="checkbox"
                        checked={confirmed}
                        onChange={(event) => setConfirmed(event.target.checked)}
                        className="mt-1 size-4 accent-[var(--brand-blue)]"
                    />
                    I have saved these somewhere I can get to without my phone.
                </label>
            </div>
        </Modal>
    );
}

/**
 * A file, without a round trip.
 *
 * The codes have never been to the server in plaintext beyond the response
 * that created them, and asking for them back would mean an endpoint that can
 * hand out recovery codes — which is the thing this design exists to avoid.
 */
export function download(filename: string, contents: string): void {
    const url = URL.createObjectURL(new Blob([contents], { type: 'text/plain' }));
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    link.click();

    URL.revokeObjectURL(url);
}
