import { useEffect, useLayoutEffect, useRef, useState } from 'react';

/** Below this many rows the DOM handles it fine and virtualising costs more. */
const THRESHOLD = 100;

/** Rows rendered beyond the viewport, so a fast scroll does not show gaps. */
const OVERSCAN = 8;

/**
 * Renders only the rows on screen, once there are enough rows for that to
 * matter.
 *
 * The catalog is the one list in this product that can be hundreds of rows of
 * eleven cells each, several of them components. Under a hundred rows this
 * returns everything unchanged — virtualisation has a cost in complexity and in
 * find-in-page, and paying it for eighty rows buys nothing.
 *
 * The window is measured off the page's own scroll rather than an inner scroll
 * container, because the table scrolls with the page: a nested scrollbar inside
 * a full-width table is a second thing to find and a second thing to lose your
 * place in.
 */
export function useVirtualRows<T>(rows: T[], rowHeight: number) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [window_, setWindow] = useState({ start: 0, end: rows.length });

    const virtualise = rows.length > THRESHOLD;

    useLayoutEffect(() => {
        if (!virtualise) {
            setWindow({ start: 0, end: rows.length });

            return;
        }

        const element = containerRef.current;
        if (!element) return;

        const measure = () => {
            const box = element.getBoundingClientRect();
            // How far the top of the table has scrolled past the viewport top.
            const scrolledPast = Math.max(0, -box.top);
            const viewport = globalThis.innerHeight;

            const start = Math.max(0, Math.floor(scrolledPast / rowHeight) - OVERSCAN);
            const end = Math.min(rows.length, Math.ceil((scrolledPast + viewport) / rowHeight) + OVERSCAN);

            setWindow((current) => (current.start === start && current.end === end ? current : { start, end }));
        };

        measure();

        globalThis.addEventListener('scroll', measure, { passive: true });
        globalThis.addEventListener('resize', measure);

        return () => {
            globalThis.removeEventListener('scroll', measure);
            globalThis.removeEventListener('resize', measure);
        };
    }, [rowHeight, rows.length, virtualise]);

    // A filter change replaces the rows under a window that was measured for
    // the old list. Resetting to the top matches what the page does anyway.
    useEffect(() => {
        if (!virtualise) setWindow({ start: 0, end: rows.length });
    }, [rows, virtualise]);

    const start = virtualise ? window_.start : 0;
    const end = virtualise ? window_.end : rows.length;

    return {
        containerRef,
        visible: rows.slice(start, end),
        spacerBefore: start * rowHeight,
        spacerAfter: Math.max(0, (rows.length - end) * rowHeight),
        virtualised: virtualise,
    };
}
