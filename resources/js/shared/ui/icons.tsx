import type { SVGProps } from 'react';

/**
 * A deliberately small inline icon set — the system needs about a dozen glyphs
 * and an icon dependency would outweigh them. All icons inherit `currentColor`
 * and sit on a 24px grid at 1.75 stroke.
 */
type IconProps = SVGProps<SVGSVGElement> & { size?: number };

function Svg({ size = 16, children, ...props }: IconProps) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
            {...props}
        >
            {children}
        </svg>
    );
}

export const CheckIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m20 6-11 11-5-5" />
    </Svg>
);
export const ChevronDownIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m6 9 6 6 6-6" />
    </Svg>
);
export const ChevronUpIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m18 15-6-6-6 6" />
    </Svg>
);
export const ChevronLeftIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m15 18-6-6 6-6" />
    </Svg>
);
export const ChevronRightIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m9 18 6-6-6-6" />
    </Svg>
);
export const XIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M18 6 6 18M6 6l12 12" />
    </Svg>
);
export const SearchIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-3.5-3.5" />
    </Svg>
);
export const PlusIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 5v14M5 12h14" />
    </Svg>
);
export const MinusIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M5 12h14" />
    </Svg>
);
export const CalendarIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M8 3v4M16 3v4M3 10h18" />
    </Svg>
);
export const ArrowUpIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 19V5M5 12l7-7 7 7" />
    </Svg>
);
export const ArrowDownIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 5v14M19 12l-7 7-7-7" />
    </Svg>
);
export const InfoIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 11v5M12 8h.01" />
    </Svg>
);
export const WarningIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        <path d="M12 9v4M12 17h.01" />
    </Svg>
);
export const DangerIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v6M12 16h.01" />
    </Svg>
);
export const SuccessIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="m8.5 12 2.5 2.5 4.5-5" />
    </Svg>
);
export const TrashIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13" />
    </Svg>
);
export const FilterIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z" />
    </Svg>
);
export const DownloadIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M4 19h16" />
    </Svg>
);
export const SortIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M8 5v14M4.5 8.5 8 5l3.5 3.5M16 19V5M12.5 15.5 16 19l3.5-3.5" />
    </Svg>
);

/** The only spinning thing in the system; used by Button's loading state. */
export function Spinner({ size = 16, className }: { size?: number; className?: string }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.25" strokeWidth="3" />
            <path
                d="M21 12a9 9 0 0 0-9-9"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                className="origin-center animate-spin"
            />
        </svg>
    );
}
