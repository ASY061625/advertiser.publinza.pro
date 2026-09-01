import { useEffect, useId, useRef, type RefObject } from 'react';

/**
 * Every open overlay, oldest first.
 *
 * Escape must close the topmost overlay only. Without a shared stack, each
 * overlay listens independently and one Escape collapses the whole pile — so a
 * modal opened from inside a drawer takes the drawer down with it.
 */
const overlayStack: string[] = [];

/**
 * Closes a popover on Escape or on a pointer press outside it.
 *
 * Listens on `pointerdown` rather than `click` so the popover closes before a
 * click lands on whatever is underneath, and only while open, so a page full of
 * closed dropdowns costs nothing.
 */
export function useDismiss<T extends HTMLElement>(open: boolean, onClose: () => void): RefObject<T> {
    const ref = useRef<T>(null);
    const id = useId();

    useEffect(() => {
        if (!open) return;

        overlayStack.push(id);

        function onPointerDown(event: PointerEvent) {
            if (ref.current && !ref.current.contains(event.target as Node)) onClose();
        }

        function onKeyDown(event: KeyboardEvent) {
            // Only the topmost overlay answers Escape.
            if (event.key === 'Escape' && overlayStack[overlayStack.length - 1] === id) {
                event.stopPropagation();
                onClose();
            }
        }

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);

            const index = overlayStack.lastIndexOf(id);
            if (index !== -1) overlayStack.splice(index, 1);
        };
    }, [open, onClose, id]);

    return ref;
}

/**
 * Traps Tab inside a container while it is open, and restores focus to whatever
 * was focused before. Used by Modal and Drawer.
 */
export function useFocusTrap<T extends HTMLElement>(open: boolean): RefObject<T> {
    const ref = useRef<T>(null);

    useEffect(() => {
        if (!open) return;

        const previous = document.activeElement as HTMLElement | null;
        const container = ref.current;

        function focusable(): HTMLElement[] {
            if (!container) return [];

            return Array.from(
                container.querySelectorAll<HTMLElement>(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                ),
            ).filter((element) => element.offsetParent !== null);
        }

        focusable()[0]?.focus();

        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 'Tab') return;

            const items = focusable();
            const first = items[0];
            const last = items[items.length - 1];
            if (!first || !last) return;

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        // The page behind an overlay must not scroll with it.
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = overflow;
            previous?.focus();
        };
    }, [open]);

    return ref;
}
