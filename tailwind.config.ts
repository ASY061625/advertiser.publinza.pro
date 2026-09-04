import type { Config } from 'tailwindcss';
import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Publinza design system.
 *
 * Every colour is a semantic alias over a CSS custom property declared in
 * `resources/css/globals.css`. Components use these names — `bg-canvas`,
 * `text-ink-700`, `border-subtle` — never a raw hex value.
 *
 * The tokens are hex, so Tailwind's opacity modifiers (`bg-card/60`) cannot
 * apply to them. Where a translucent surface is needed there is a dedicated
 * token instead (`bg-overlay`, `bg-row-hover`).
 */
export default {
    content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{ts,tsx}'],
    theme: {
        extend: {
            colors: {
                // --- Brand ---------------------------------------------------
                brand: {
                    DEFAULT: 'var(--brand-blue)',
                    hover: 'var(--brand-blue-700)',
                    pressed: 'var(--brand-pressed)',
                    subtle: 'var(--brand-blue-50)',
                },
                teal: { DEFAULT: 'var(--teal)', subtle: 'var(--teal-50)' },
                gold: { DEFAULT: 'var(--gold)', subtle: 'var(--gold-50)' },

                // --- Ink -----------------------------------------------------
                ink: {
                    900: 'var(--ink-900)',
                    700: 'var(--ink-700)',
                    500: 'var(--ink-500)',
                    300: 'var(--ink-300)',
                },

                // --- Surfaces ------------------------------------------------
                canvas: 'var(--surface-canvas)',
                card: 'var(--surface-card)',
                sunken: 'var(--surface-sunken)',
                overlay: 'var(--overlay)',
                'row-hover': 'var(--row-hover)',

                // --- Semantic ------------------------------------------------
                success: { DEFAULT: 'var(--success)', bg: 'var(--success-bg)' },
                warning: {
                    DEFAULT: 'var(--warning)',
                    bg: 'var(--warning-bg)',
                    'bg-hover': 'var(--warning-bg-hover)',
                },
                danger: {
                    DEFAULT: 'var(--danger)',
                    bg: 'var(--danger-bg)',
                    pressed: 'var(--danger-pressed)',
                },
                info: { DEFAULT: 'var(--info)', bg: 'var(--info-bg)' },

                // --- Status, fixed product-wide ------------------------------
                status: {
                    'draft-bg': 'var(--status-draft-bg)',
                    'draft-fg': 'var(--status-draft-fg)',
                    'new-bg': 'var(--status-new-bg)',
                    'new-fg': 'var(--status-new-fg)',
                    'progress-bg': 'var(--status-progress-bg)',
                    'progress-fg': 'var(--status-progress-fg)',
                    'review-bg': 'var(--status-review-bg)',
                    'review-fg': 'var(--status-review-fg)',
                    'posted-bg': 'var(--status-posted-bg)',
                    'posted-fg': 'var(--status-posted-fg)',
                    'frozen-bg': 'var(--status-frozen-bg)',
                    'frozen-fg': 'var(--status-frozen-fg)',
                    'rejected-bg': 'var(--status-rejected-bg)',
                    'rejected-fg': 'var(--status-rejected-fg)',
                    'refunded-bg': 'var(--status-refunded-bg)',
                    'refunded-fg': 'var(--status-refunded-fg)',
                },
            },

            borderColor: {
                DEFAULT: 'var(--ink-300)',
                subtle: 'var(--ink-300)',
                strong: 'var(--ink-500)',
                brand: 'var(--brand-blue)',
                danger: 'var(--danger)',
            },

            fontFamily: {
                sora: ['Sora', ...defaultTheme.fontFamily.sans], // headings and UI
                sans: ['Inter', ...defaultTheme.fontFamily.sans], // body and tables
            },

            // The full scale. Nothing outside these eight steps.
            fontSize: {
                xs: ['12px', { lineHeight: '1.5' }],
                sm: ['13px', { lineHeight: '1.5' }],
                base: ['14px', { lineHeight: '1.5' }],
                md: ['16px', { lineHeight: '1.5' }],
                lg: ['20px', { lineHeight: '1.2' }],
                xl: ['26px', { lineHeight: '1.2' }],
                '2xl': ['34px', { lineHeight: '1.2' }],
                '3xl': ['44px', { lineHeight: '1.2' }],
            },

            borderRadius: {
                button: '6px',
                card: '8px',
                input: '8px',
                pill: '999px',
            },

            // One shadow token only.
            boxShadow: {
                card: 'var(--shadow-card)',
            },

            spacing: {
                sidebar: '248px',
                'sidebar-collapsed': '68px',
                header: '60px',
                'row-catalog': '56px',
                row: '48px',
                drawer: '480px',
            },

            maxWidth: { content: '1440px', drawer: '480px' },

            /*
             * One extra breakpoint, for one thing: the catalog's filter rail.
             *
             * The rail is 280px and stops fitting beside the results at about
             * 1100px — which is between Tailwind's lg and xl. Naming it here
             * keeps the number in the design system rather than as a magic
             * `min-[1100px]:` sprinkled across three components.
             */
            screens: { rail: '1100px' },

            transitionDuration: {
                fast: 'var(--motion-fast)',
                drawer: 'var(--motion-drawer)',
                toast: 'var(--motion-toast)',
            },

            transitionTimingFunction: { standard: 'var(--ease)' },

            keyframes: {
                'slide-in-right': {
                    from: { transform: 'translateX(100%)' },
                    to: { transform: 'translateX(0)' },
                },
                'fade-in': { from: { opacity: '0' }, to: { opacity: '1' } },
                'scale-in': {
                    from: { opacity: '0', transform: 'scale(.97)' },
                    to: { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    from: { backgroundPosition: '-200% 0' },
                    to: { backgroundPosition: '200% 0' },
                },
                spin: { to: { transform: 'rotate(360deg)' } },
            },

            animation: {
                'slide-in-right': 'slide-in-right var(--motion-drawer) var(--ease)',
                'fade-in': 'fade-in var(--motion-fast) var(--ease)',
                'scale-in': 'scale-in var(--motion-fast) var(--ease)',
                'toast-in': 'scale-in var(--motion-toast) var(--ease)',
                shimmer: 'shimmer 1.4s linear infinite',
                spin: 'spin .7s linear infinite',
            },
        },
    },
    plugins: [],
} satisfies Config;
