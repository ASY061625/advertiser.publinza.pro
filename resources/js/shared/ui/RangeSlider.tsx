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
    disabled,
    error,
    className,
}: RangeSliderProps) {
    const id = useId();
    const trackRef = useRef<HTMLDivElement>(null);
    const [low, high] = value;

    const pct = useCallback((v: number) => ((v - min) / (max - min)) * 100, [min, max]);

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
        const ratio = (event.clientX - rect.left) / rect.width;
        const raw = min + ratio * (max - min);
        const snapped = Math.round(raw / step) * step;

        if (Math.abs(snapped - low) <= Math.abs(snapped - high)) setLow(snapped);
        else setHigh(snapped);
    }

    const thumb = cn(
        'pointer-events-none absolute inset-0 h-6 w-full appearance-none bg-transparent',
        '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-4',
        '[&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-pill',
        '[&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-brand',
        '[&::-webkit-slider-thumb]:bg-card [&::-webkit-slider-thumb]:shadow-card',
        '[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-4',
        '[&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-pill',
        '[&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-brand [&::-moz-range-thumb]:bg-card',
        'disabled:[&::-webkit-slider-thumb]:border-ink-300 disabled:[&::-moz-range-thumb]:border-ink-300',
    );

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm font-medium text-ink-700">{label}</span>
                <span className="num text-sm text-ink-500">
                    {format(low)} – {format(high)}
                </span>
            </div>

            <div className="relative h-6" onPointerDown={onTrackPointerDown}>
                <div ref={trackRef} className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-pill bg-sunken">
                    <div
                        className={cn('absolute h-full rounded-pill', disabled ? 'bg-ink-300' : 'bg-brand')}
                        style={{ left: `${pct(low)}%`, width: `${pct(high) - pct(low)}%` }}
                    />
                </div>

                <input
                    type="range"
                    aria-label={`${label} minimum`}
                    min={min}
                    max={max}
                    step={step}
                    value={low}
                    disabled={disabled}
                    onChange={(event) => setLow(Number(event.target.value))}
                    className={thumb}
                />
                <input
                    type="range"
                    aria-label={`${label} maximum`}
                    min={min}
                    max={max}
                    step={step}
                    value={high}
                    disabled={disabled}
                    onChange={(event) => setHigh(Number(event.target.value))}
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
