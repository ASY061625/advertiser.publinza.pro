import { Button } from '@shared/ui';
import { cn } from '@shared/lib/cn';

interface Props {
    dirty: boolean;
    processing: boolean;
    onSave: () => void;
    onReset: () => void;
    /** Replaces "Save changes" where the verb should be more specific. */
    label?: string;
}

/**
 * The per-tab action bar.
 *
 * Sticky to the bottom of the viewport and only present once something has
 * changed. A Save button that is always there but usually does nothing trains
 * people to press it and wonder; one that appears when there is something to
 * save is itself the answer to "did that take".
 */
export function SaveBar({ dirty, processing, onSave, onReset, label = 'Save changes' }: Props) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 -mx-4 mt-2 border-t border-subtle bg-card/95 px-4 py-3 backdrop-blur lg:-mx-6 lg:px-6',
                'transition-opacity duration-fast',
                dirty ? 'opacity-100' : 'pointer-events-none opacity-0',
            )}
            aria-hidden={!dirty}
        >
            <div className="flex flex-wrap items-center justify-end gap-3">
                <p className="mr-auto text-sm text-ink-500">Unsaved changes</p>

                <Button variant="secondary" onClick={onReset} disabled={processing}>
                    Discard
                </Button>

                <Button onClick={onSave} loading={processing}>
                    {label}
                </Button>
            </div>
        </div>
    );
}
