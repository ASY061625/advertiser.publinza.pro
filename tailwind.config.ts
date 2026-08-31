import type { Config } from 'tailwindcss';
import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Publinza design system.
 *
 * Every colour here is a thin alias over a CSS custom property declared in
 * `resources/css/app.css`. The tokens are the single source of truth — do not
 * introduce raw hex values in components.
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    DEFAULT: 'var(--brand-blue)',
                    blue: 'var(--brand-blue)',
                    700: 'var(--brand-blue-700)',
                    50: 'var(--brand-blue-50)',
                },
                teal: {
                    DEFAULT: 'var(--teal)',
                    50: 'var(--teal-50)',
                },
                gold: {
                    DEFAULT: 'var(--gold)',
                    50: 'var(--gold-50)',
                },
                ink: {
                    900: 'var(--ink-900)',
                    700: 'var(--ink-700)',
                    500: 'var(--ink-500)',
                    300: 'var(--ink-300)',
                },
                surface: {
                    canvas: 'var(--surface-canvas)',
                    card: 'var(--surface-card)',
                    sunken: 'var(--surface-sunken)',
                },
                success: { DEFAULT: 'var(--success)', bg: 'var(--success-bg)' },
                warning: { DEFAULT: 'var(--warning)', bg: 'var(--warning-bg)' },
                danger: { DEFAULT: 'var(--danger)', bg: 'var(--danger-bg)' },
                info: { DEFAULT: 'var(--info)', bg: 'var(--info-bg)' },
            },
            fontFamily: {
                // Headings and UI chrome.
                sora: ['Sora', ...defaultTheme.fontFamily.sans],
                // Body, tables, forms.
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                // The full scale — nothing outside these steps.
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
            boxShadow: {
                // The one shadow.
                card: '0 1px 2px rgba(11,27,51,.06), 0 4px 12px rgba(11,27,51,.04)',
            },
            spacing: {
                sidebar: '248px',
                'sidebar-collapsed': '68px',
                header: '60px',
                'row-catalog': '56px',
                row: '48px',
            },
            maxWidth: {
                content: '1440px',
            },
            transitionDuration: {
                drawer: '180ms',
                row: '150ms',
                toast: '200ms',
            },
        },
    },
    plugins: [],
} satisfies Config;
