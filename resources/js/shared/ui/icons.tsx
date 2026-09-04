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

export const HomeIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1Z" />
    </Svg>
);
export const FolderIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
    </Svg>
);
export const GlobeIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
    </Svg>
);
export const ListIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
    </Svg>
);
export const SparkleIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 3.5 13.7 9l5.5 1.7-5.5 1.8L12 18l-1.7-5.5L4.8 10.7 10.3 9Z" />
        <path d="M18.5 3.5v3M20 5h-3" />
    </Svg>
);
export const HeartIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 20s-7-4.3-7-9.2A4 4 0 0 1 12 8a4 4 0 0 1 7 2.8C19 15.7 12 20 12 20Z" />
    </Svg>
);
export const ExternalLinkIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M14 4h6v6M20 4l-9 9" />
        <path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
    </Svg>
);
export const CopyIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="9" y="9" width="11" height="11" rx="2" />
        <path d="M5 15a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2" />
    </Svg>
);
export const ChatIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M21 12a7.5 7.5 0 0 1-7.5 7.5H8l-4 2.5v-4.4A7.5 7.5 0 0 1 11.5 4.5h2A7.5 7.5 0 0 1 21 12Z" />
    </Svg>
);
export const CartIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 4h2l2.2 10.5a2 2 0 0 0 2 1.6h7.5a2 2 0 0 0 2-1.5L20.5 8H6" />
        <circle cx="10" cy="20" r="1" />
        <circle cx="17" cy="20" r="1" />
    </Svg>
);
export const WalletIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
        <path d="M16 12h4v4h-4a2 2 0 0 1 0-4Z" />
    </Svg>
);
export const MenuIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 7h16M4 12h16M4 17h16" />
    </Svg>
);
export const MoreIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="5" cy="12" r="1" />
        <circle cx="12" cy="12" r="1" />
        <circle cx="19" cy="12" r="1" />
    </Svg>
);
export const LogOutIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M15 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h9M18 15l3-3-3-3M21 12h-9" />
    </Svg>
);
export const PanelLeftIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <path d="M9.5 4v16" />
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
