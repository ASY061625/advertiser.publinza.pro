interface Props {
    values: number[];
    /** Names the measure in the accessible summary. */
    label: string;
}

const WIDTH = 68;
const HEIGHT = 20;

/**
 * Twelve months of one measure, at tile size.
 *
 * One series, one hue, no axes, no legend — the tile's label names the measure
 * and the tile's own figure is the current value, so the line's only job is the
 * shape between them.
 *
 * There is no hover tooltip, and that is deliberate rather than skipped. The
 * whole plot is 68×20 css pixels: a hit target smaller than a fingertip, on a
 * chart whose headline number is already printed two lines above it. What
 * replaces it is an accessible summary — direction and size of the change, in
 * words — which serves a screen reader, a keyboard, and a touch screen equally,
 * and which a tooltip on a 20px target would serve none of.
 */
export function Sparkline({ values, label }: Props) {
    if (values.length < 2) return null;

    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min;

    // A flat series still draws a line, through the middle. Scaling a zero span
    // would divide by zero; drawing nothing would lose "this has not moved",
    // which is itself a finding.
    const y = (value: number) => (span === 0 ? HEIGHT / 2 : HEIGHT - 2 - ((value - min) / span) * (HEIGHT - 4));
    const x = (index: number) => (index / (values.length - 1)) * WIDTH;

    const path = values.map((value, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(value)}`).join(' ');
    const last = values[values.length - 1]!;
    const first = values[0]!;

    return (
        <svg
            width={WIDTH}
            height={HEIGHT}
            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
            role="img"
            aria-label={summarise(label, first, last, values.length)}
            className="overflow-visible"
        >
            <title>{summarise(label, first, last, values.length)}</title>

            <path
                d={path}
                fill="none"
                stroke="var(--brand-blue)"
                strokeWidth={1.5}
                strokeLinecap="round"
                strokeLinejoin="round"
            />

            {/* The newest point, marked. Which end is "now" is the one thing a
                line this small cannot say on its own. */}
            <circle cx={x(values.length - 1)} cy={y(last)} r={2} fill="var(--brand-blue)" />
        </svg>
    );
}

/** "Monthly traffic, up 34% over 12 months." */
function summarise(label: string, first: number, last: number, points: number): string {
    const months = `${points} month${points === 1 ? '' : 's'}`;

    if (first === 0) {
        return last === 0
            ? `${label}, unchanged over ${months}.`
            : `${label}, from nothing to ${last.toLocaleString('en-US')} over ${months}.`;
    }

    const change = Math.round(((last - first) / first) * 100);

    if (change === 0) return `${label}, unchanged over ${months}.`;

    return `${label}, ${change > 0 ? 'up' : 'down'} ${Math.abs(change)}% over ${months}.`;
}
