import { useState } from 'react';
import { Alert, Button, Input, Modal, Textarea } from '@shared/ui';

interface Props {
    open: boolean;
    /** How many posts the confirmation covers; 1 for a single row action. */
    count: number;
    onClose: () => void;
    onConfirm: (reason: string) => void;
}

const PHRASE = 'CANCEL';

/**
 * Typed confirmation for cancelling a post.
 *
 * Cancelling is not undoable and it moves money, so it asks for a word to be
 * typed rather than a second click — a click can be muscle memory, typing
 * CANCEL cannot. The reason is required because it is written into the post's
 * history, which is what anyone reading the thread later actually needs.
 */
export function CancelDialog({ open, count, onClose, onConfirm }: Props) {
    const [phrase, setPhrase] = useState('');
    const [reason, setReason] = useState('');
    const [touched, setTouched] = useState(false);

    const reasonTooShort = reason.trim().length < 3;
    const ready = phrase.trim().toUpperCase() === PHRASE && !reasonTooShort;

    function close() {
        setPhrase('');
        setReason('');
        setTouched(false);
        onClose();
    }

    return (
        <Modal
            open={open}
            onClose={close}
            title={count === 1 ? 'Cancel this post' : `Cancel ${count} posts`}
            footer={
                <>
                    <Button variant="secondary" onClick={close}>
                        Keep {count === 1 ? 'it' : 'them'}
                    </Button>
                    <Button
                        variant="danger"
                        disabled={!ready}
                        onClick={() => {
                            onConfirm(reason.trim());
                            close();
                        }}
                    >
                        Cancel {count === 1 ? 'post' : `${count} posts`}
                    </Button>
                </>
            }
        >
            <Alert tone="warning" title="This cannot be undone.">
                {count === 1
                    ? 'Any money held for this post is returned to your wallet, and the placement is not made.'
                    : `Any money held for these posts is returned to your wallet. Posts that are already live cannot be cancelled and will be left alone.`}
            </Alert>

            <div className="mt-4 flex flex-col gap-4">
                <Textarea
                    label="Why are you cancelling?"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    onBlur={() => setTouched(true)}
                    rows={3}
                    hint="Saved to the post's history, so the reason is still there in a month."
                    error={touched && reasonTooShort ? 'Give a short reason — a few words is enough.' : undefined}
                />

                <Input
                    label={`Type ${PHRASE} to confirm`}
                    value={phrase}
                    onChange={(event) => setPhrase(event.target.value)}
                    autoComplete="off"
                    spellCheck={false}
                />
            </div>
        </Modal>
    );
}
