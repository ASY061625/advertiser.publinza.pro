import { useCallback, useId, useRef, type PointerEvent as ReactPointerEvent } from 'react';
import { cn } from '@shared/lib/cn';
import { NumberInput } from './NumberInput';

export interface RangeSliderProps {
    label: string;
    min: number;
    max: number;
    step?: number;
    value: [number, number];
    onChange: (value: [number, number]) => void;
    /** Formats the value shown above the track. */
    format?: (value: number) => string;
    /** Renders the paired min/max number inputs beneath the track. */
    showInputs?: boolean;
    /**
     * Bar heights drawn behind the track, left to right across the full range.
     * Scaled to the tallest bar, so the shape is readable whatever the counts.
     */
    histogram?: number[];
    /**
     * Positions the handles on a logarithmic scale.
     *
     * For a measure whose useful decisions are "about a thousand" and "about a
     * hundred thousand", a linear track spends nine tenths of its width on
     * values nobody is choosing between. The values either side of the
     * component are unchanged — only where they sit on the track moves.
     */
    scale?: 'linear' | 'log';
    disabled?: boolean;
    error?: string;
    className?: string;
}

/**
 * Dual-handle range with paired min/max number inputs.
 *
 * The two handles are real range inputs stacked on one track, so keyboard
 * support, screen-reader announcement and touch targets are native. They are
 * clamped against each other so the low handle can never cross the high one.
 */
export function RangeSlider({
    label,
    min,
    max,
    step = 1,
    value,
    onChange,
    format = (v) => v.toLocaleString('en-US'),
    showInputs = true,
    histogram,
    scale = 'linear',
    disabled,
    error,
    className,
}: RangeSliderProps) {
    const id = useId();
    const trackRef = useRef<HTMLDivElement>(null);
    const [low, high] = value;

    // Where a value sits on the track, and what a position on the track means.
    // The two are inverses; on a linear scale both are the identity.
    const pct = useCallback(
        (v: number) => (scale === 'log' ? logPct(v, min, max) : ((v - min) / (max - min)) * 100),
        [min, max, scale],
    );

    const fromPct = useCallback(
        (ratio: number) => (scale === 'log' ? logValue(ratio, min, max) : min + ratio * (max - min)),
        [min, max, scale],
    );

    const tallest = histogram && histogram.length > 0 ? Math.max(...histogram, 1) : 1;

    function setLow(next: number) {
        onChange([Math.min(Math.max(next, min), high), high]);
    }

    function setHigh(next: number) {
        onChange([low, Math.max(Math.min(next, max), low)]);
    }

    /** Clicking the track moves whichever handle is nearer. */
    function onTrackPointerDown(event: ReactPointerEvent<HTMLDivElement>) {
        if (disabled || !trackRef.current) return;

        const rect = trackRef.current.getBoundingClientRect();
        const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
        const snapped = Math.round(fromPct(ratio) / step) * step;

        if (Math.abs(snapped - low) <= Math.abs(snapped - high)) setLow(snapped);
        else setHigh(snapped);
    }

    const thumb = cn(
        'pointer-events-none absolute inset-x-0 h-6 w-full appearance-none bg-transparent',
        histogram ? 'top-6' : 'inset-y-0',
        '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-4',
        '[&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-pill',
        '[&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-brand',
        '[&::-webkit-slider-thumb]:bg-card [&::-webkit-slider-thumb]:shadow-card',
        '[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-4',
        '[&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-pill',
        '[&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-brand [&::-moz-range-thumb]:bg-card',
        'disabled:[&::-webkit-slider-thumb]:border-ink-300 disabled:[&::-moz-range-thumb]:border-ink-300',
    );

    /**
     * A native range input is linear, so on a log scale the element holds a
     * 0–1000 position rather than the value. The value the caller sees never
     * changes; only what the browser is asked to interpolate.
     */
    const toSlider = (v: number) => (scale === 'log' ? Math.round(pct(v) * 10) : v);
    const fromSlider = (v: number) => (scale === 'log' ? Math.round(fromPct(v / 1000)) : v);
    const sliderMin = scale === 'log' ? 0 : min;
    const sliderMax = scale === 'log' ? 1000 : max;
    const sliderStep = scale === 'log' ? 1 : step;

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm font-medium text-ink-700">{label}</span>
                <span className="num text-sm text-ink-500">
                    {format(low)} – {format(high)}
                </span>
            </div>

            <div className={cn('relative', histogram ? 'h-14' : 'h-6')} onPointerDown={onTrackPointerDown}>
                {histogram && (
                    // Behind the track, not on it: the bars are context for
                    // where to put the handles, and drawing them at full
                    // strength would compete with the selection they sit under.
                    <div aria-hidden="true" className="absolute inset-x-0 top-0 flex h-8 items-end gap-px">
                        {histogram.map((count, index) => {
                            const at = (index + 0.5) / histogram.length;
                            const inside = at >= pct(low) / 100 && at <= pct(high) / 100;

                            return (
                                <span
                                    key={index}
                                    className={cn('flex-1 rounded-t-[2px]', inside ? 'bg-brand' : 'bg-ink-300')}
                                    style={{
                                        height: `${Math.max(count > 0 ? 8 : 2, (count / tallest) * 100)}%`,
                                        opacity: inside ? 0.35 : 0.5,
                                    }}
                                />
                            );
                        })}
                    </div>
                )}

                <div
                    ref={trackRef}
                    className={cn(
                        'absolute inset-x-0 h-1 rounded-pill bg-sunken',
                        histogram ? 'top-9' : 'top-1/2 -translate-y-1/2',
                    )}
                >
                    <div
                        className={cn('absolute h-full rounded-pill', disabled ? 'bg-ink-300' : 'bg-brand')}
                        style={{ left: `${pct(low)}%`, width: `${pct(high) - pct(low)}%` }}
                    />
                </div>

                <input
                    type="range"
                    aria-label={`${label} minimum`}
                    aria-valuetext={format(low)}
                    min={sliderMin}
                    max={sliderMax}
                    step={sliderStep}
                    value={toSlider(low)}
                    disabled={disabled}
                    onChange={(event) => setLow(fromSlider(Number(event.target.value)))}
                    className={thumb}
                />
                <input
                    type="range"
                    aria-label={`${label} maximum`}
                    aria-valuetext={format(high)}
                    min={sliderMin}
                    max={sliderMax}
                    step={sliderStep}
                    value={toSlider(high)}
                    disabled={disabled}
                    onChange={(event) => setHigh(fromSlider(Number(event.target.value)))}
                    className={thumb}
                />
            </div>

            {showInputs && (
                <div className="grid grid-cols-2 gap-3">
                    <NumberInput
                        id={`${id}-min`}
                        label="Minimum"
                        value={low}
                        min={min}
                        max={high}
                        step={step}
                        disabled={disabled}
                        onValueChange={(next) => setLow(next === '' ? min : next)}
                    />
                    <NumberInput
                        id={`${id}-max`}
                        label="Maximum"
                        value={high}
                        min={low}
                        max={max}
                        step={step}
                        disabled={disabled}
                        onValueChange={(next) => setHigh(next === '' ? max : next)}
                    />
                </div>
            )}

            {error && (
                <p role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}

/**
 * Log positioning that survives a zero floor.
 *
 * Traffic ranges start at 0 and log(0) is undefined, so the scale is shifted by
 * one. The shift is invisible at catalog magnitudes and is what stops the whole
 * track collapsing the moment a site with no measured traffic sets the floor.
 */
function logPct(value: number, min: number, max: number): number {
    const lo = Math.log(min + 1);
    const hi = Math.log(max + 1);

    if (hi <= lo) return 0;

    return Math.min(100, Math.max(0, ((Math.log(Math.max(min, value) + 1) - lo) / (hi - lo)) * 100));
}

function logValue(ratio: number, min: number, max: number): number {
    const lo = Math.log(min + 1);
    const hi = Math.log(max + 1);

    return Math.round(Math.exp(lo + ratio * (hi - lo)) - 1);
}
