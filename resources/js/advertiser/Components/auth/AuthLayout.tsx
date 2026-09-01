import { useEffect, useState, type ReactNode } from 'react';
import { Head } from '@inertiajs/react';

interface AuthLayoutProps {
    title: string;
    heading: string;
    /** One line under the heading. Not a paragraph. */
    subheading?: ReactNode;
    /** Real catalog statistics, rotated one at a time on the left panel. */
    proofLines: string[];
    children: ReactNode;
    /** Sits under the form, usually the link to the other side of the flow. */
    footer?: ReactNode;
}

const ROTATE_MS = 6000;

/**
 * The split screen every auth page sits in.
 *
 * Left 44% is the deep ink panel; right 56% carries the form. Below `lg` the
 * panel collapses to a 72px strip so the keyboard does not fight the form for
 * what is left of a phone screen.
 */
export function AuthLayout({ title, heading, subheading, proofLines, children, footer }: AuthLayoutProps) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (proofLines.length < 2) return;

        // Rotation is decorative. Someone who has asked for less motion gets
        // the first line and nothing moving.
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) return;

        const timer = window.setInterval(() => setIndex((current) => (current + 1) % proofLines.length), ROTATE_MS);

        return () => window.clearInterval(timer);
    }, [proofLines.length]);

    return (
        <div className="flex min-h-screen flex-col lg:flex-row">
            <Head title={title} />

            {/* Left panel — a 72px strip on small screens, 44% of the width from lg. */}
            <aside className="flex h-[72px] shrink-0 items-center bg-ink-900 px-6 lg:h-auto lg:w-[44%] lg:flex-col lg:items-start lg:justify-between lg:px-12 lg:py-14">
                <a href="/" className="font-sora text-md font-semibold text-white lg:text-lg">
                    Publinza
                </a>

                {/* The proof line is hidden on the strip: 72px is not enough for
                    it to be read, and truncating a claim is worse than omitting it. */}
                <div className="hidden lg:block lg:max-w-sm">
                    <p
                        key={index}
                        aria-live="polite"
                        className="animate-fade-in font-sora text-lg font-medium leading-snug text-white"
                    >
                        {proofLines[index]}
                    </p>

                    {proofLines.length > 1 && (
                        <div className="mt-6 flex gap-1.5" aria-hidden="true">
                            {proofLines.map((line, i) => (
                                <span
                                    key={line}
                                    className={`h-1 rounded-pill transition-all duration-toast ${
                                        i === index ? 'w-6 bg-teal' : 'w-1.5 bg-white/30'
                                    }`}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <p className="hidden text-sm text-ink-300 lg:block">Publinza Media Ltd · Dublin, Ireland</p>
            </aside>

            {/* Right panel — the form. */}
            <main className="flex flex-1 items-center justify-center bg-card px-6 py-12 lg:w-[56%]">
                <div className="w-full max-w-[400px]">
                    <h1 className="font-sora text-xl font-semibold text-ink-900">{heading}</h1>
                    {subheading && <p className="mt-2 text-base text-ink-700">{subheading}</p>}

                    <div className="mt-8">{children}</div>

                    {footer && <div className="mt-6 text-base text-ink-700">{footer}</div>}
                </div>
            </main>
        </div>
    );
}
