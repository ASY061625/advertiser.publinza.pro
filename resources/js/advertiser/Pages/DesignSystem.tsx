import { Head } from '@inertiajs/react';
import { ToastProvider } from '@shared/ui';
import { FoundationsSection } from '../Components/design-system/FoundationsSection';
import { ButtonsSection } from '../Components/design-system/ButtonsSection';
import { FormsSection } from '../Components/design-system/FormsSection';
import { DataSection } from '../Components/design-system/DataSection';
import { FeedbackSection } from '../Components/design-system/FeedbackSection';

const NAV = [
    { id: 'color', label: 'Color' },
    { id: 'typography', label: 'Typography' },
    { id: 'shape', label: 'Shape & motion' },
    { id: 'button', label: 'Button' },
    { id: 'icon-button', label: 'IconButton' },
    { id: 'badge', label: 'Badge' },
    { id: 'avatar', label: 'Avatar' },
    { id: 'tooltip', label: 'Tooltip' },
    { id: 'breadcrumb', label: 'Breadcrumb' },
    { id: 'input', label: 'Input' },
    { id: 'number-input', label: 'NumberInput' },
    { id: 'textarea', label: 'Textarea' },
    { id: 'select', label: 'Select' },
    { id: 'multiselect', label: 'MultiSelect' },
    { id: 'combobox', label: 'Combobox' },
    { id: 'range-slider', label: 'RangeSlider' },
    { id: 'choice', label: 'Checkbox, Radio, Switch' },
    { id: 'dates', label: 'Date pickers' },
    { id: 'quantbar', label: 'QuantBar' },
    { id: 'statcard', label: 'StatCard' },
    { id: 'card', label: 'Card' },
    { id: 'tabs', label: 'Tabs' },
    { id: 'table', label: 'Table' },
    { id: 'pagination', label: 'Pagination' },
    { id: 'alert', label: 'Alert' },
    { id: 'toast', label: 'Toast' },
    { id: 'progress', label: 'ProgressBar' },
    { id: 'skeleton', label: 'Skeleton' },
    { id: 'empty', label: 'EmptyState' },
    { id: 'overlays', label: 'Modal, Drawer, Dropdown' },
];

/**
 * The design system gallery: every component in every state, light theme only.
 *
 * This route is registered in routes/app.php and is never available in
 * production — see the guard there.
 */
export default function DesignSystem() {
    return (
        <ToastProvider>
            <Head title="Design system" />

            <div className="min-h-screen bg-canvas">
                <header className="sticky top-0 z-30 flex h-header items-center border-b border-subtle bg-card px-6">
                    <h1 className="font-sora text-md font-semibold text-ink-900">Publinza design system</h1>
                    <span className="ml-3 rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500">
                        Light theme only
                    </span>
                </header>

                <div className="mx-auto flex max-w-content gap-8 px-6 py-8">
                    <nav aria-label="Components" className="sticky top-[76px] hidden h-fit w-56 shrink-0 lg:block">
                        <ul className="flex flex-col gap-0.5">
                            {NAV.map((item) => (
                                <li key={item.id}>
                                    <a
                                        href={`#${item.id}`}
                                        className="block rounded-button px-3 py-1.5 text-base text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-900"
                                    >
                                        {item.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <main className="flex min-w-0 flex-1 flex-col gap-12 pb-24">
                        <p className="max-w-2xl text-md text-ink-700">
                            Every component in every state. Tokens come from{' '}
                            <code className="rounded bg-sunken px-1 text-base">resources/css/globals.css</code> and are
                            aliased as semantic Tailwind names in{' '}
                            <code className="rounded bg-sunken px-1 text-base">tailwind.config.ts</code>. Hover,
                            focus-visible and active are live — tab through the page to see the focus ring.
                        </p>

                        <FoundationsSection />
                        <ButtonsSection />
                        <FormsSection />
                        <DataSection />
                        <FeedbackSection />
                    </main>
                </div>
            </div>
        </ToastProvider>
    );
}
