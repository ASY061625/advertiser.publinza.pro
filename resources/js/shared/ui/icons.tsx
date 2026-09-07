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
// A flag, for the wishlist's priority marker. Sparkle already means "new" in
// the header, and a second meaning on the same glyph is one the reader has to
// learn per screen.
export const FlagIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M5.5 21V4" />
        <path d="M5.5 5.2h11.8l-2.1 3.6 2.1 3.6H5.5Z" />
    </Svg>
);
// The delivered/read ticks on a sent message. Two glyphs rather than one that
// changes colour: colour alone is not a state anyone can name.
export const CheckDoubleIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m1.5 12.5 4 4L13 9" />
        <path d="m11 16.5 4-4M11.5 6.5 17 12" />
    </Svg>
);
export const PaperclipIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.5-2.5l7.3-7.3" />
    </Svg>
);
export const SmileIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="M8.5 14.5a4.5 4.5 0 0 0 7 0" />
        <path d="M9 9.5h.01M15 9.5h.01" />
    </Svg>
);
export const SendIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M20.5 3.5 10.5 13.5" />
        <path d="M20.5 3.5 14 20.5l-3.5-7-7-3.5Z" />
    </Svg>
);
export const BellOffIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M8.7 4.6A5.5 5.5 0 0 1 17.5 9c0 2.4.5 4.1 1.1 5.3M5.9 8a5.5 5.5 0 0 0-.4 1c0 4-1.5 5-1.5 6h12" />
        <path d="M10.3 20a2 2 0 0 0 3.4 0" />
        <path d="m3 3 18 18" />
    </Svg>
);
export const ArchiveIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 7.5h18v12H3z" />
        <path d="M2 4.5h20v3H2zM9.5 11.5h5" />
    </Svg>
);
export const MailIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 5.5h18v13H3z" />
        <path d="m3 6.5 9 6 9-6" />
    </Svg>
);
// A locked switch: a notification that is a receipt, not a preference.
export const LockIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M5.5 10.5h13v9.5h-13z" />
        <path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3" />
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
export const BellIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
    </Svg>
);

export const ClockIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
    </Svg>
);

export const DocumentIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
        <path d="M14 3v5h5M9 13h6M9 17h4" />
    </Svg>
);

export const ChartIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 3v18h18" />
        <path d="M7 15v-4M12 15V7M17 15v-6" />
    </Svg>
);

export const TagIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M20.6 13.4 12 22l-9-9V4a1 1 0 0 1 1-1h8z" />
        <circle cx="7.5" cy="7.5" r="1.2" />
    </Svg>
);

export const ReceiptIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M5 3h14v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21z" />
        <path d="M9 8h6M9 12h6" />
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
