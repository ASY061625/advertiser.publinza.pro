import { Panel, Row, Section, Swatch } from './Shell';

const BRAND = [
    { name: 'Brand blue', token: '--brand-blue', className: 'bg-brand' },
    { name: 'Brand hover', token: '--brand-blue-700', className: 'bg-brand-hover' },
    { name: 'Brand subtle', token: '--brand-blue-50', className: 'bg-brand-subtle' },
    { name: 'Teal', token: '--teal', className: 'bg-teal' },
    { name: 'Teal subtle', token: '--teal-50', className: 'bg-teal-subtle' },
    { name: 'Gold', token: '--gold', className: 'bg-gold' },
    { name: 'Gold subtle', token: '--gold-50', className: 'bg-gold-subtle' },
];

const INK = [
    { name: 'Ink 900', token: '--ink-900', className: 'bg-ink-900' },
    { name: 'Ink 700', token: '--ink-700', className: 'bg-ink-700' },
    { name: 'Ink 500', token: '--ink-500', className: 'bg-ink-500' },
    { name: 'Ink 300', token: '--ink-300', className: 'bg-ink-300' },
    { name: 'Canvas', token: '--surface-canvas', className: 'bg-canvas' },
    { name: 'Card', token: '--surface-card', className: 'bg-card' },
    { name: 'Sunken', token: '--surface-sunken', className: 'bg-sunken' },
];

const SEMANTIC = [
    { name: 'Success', token: '--success', className: 'bg-success' },
    { name: 'Success bg', token: '--success-bg', className: 'bg-success-bg' },
    { name: 'Warning', token: '--warning', className: 'bg-warning' },
    { name: 'Warning bg', token: '--warning-bg', className: 'bg-warning-bg' },
    { name: 'Danger', token: '--danger', className: 'bg-danger' },
    { name: 'Danger bg', token: '--danger-bg', className: 'bg-danger-bg' },
    { name: 'Info', token: '--info', className: 'bg-info' },
    { name: 'Info bg', token: '--info-bg', className: 'bg-info-bg' },
];

const SCALE = [
    { size: '44px', className: 'text-3xl', use: 'Marketing hero' },
    { size: '34px', className: 'text-2xl', use: 'Page title, stat value' },
    { size: '26px', className: 'text-xl', use: 'Section title' },
    { size: '20px', className: 'text-lg', use: 'Card and panel title' },
    { size: '16px', className: 'text-md', use: 'Marketing body, modal title' },
    { size: '14px', className: 'text-base', use: 'App body, table cell' },
    { size: '13px', className: 'text-sm', use: 'Labels, hints, table header' },
    { size: '12px', className: 'text-xs', use: 'Badges, counts' },
];

export function FoundationsSection() {
    return (
        <>
            <Section
                id="color"
                title="Color"
                note="Tokens live in globals.css and are aliased in tailwind.config.ts under semantic names. Components never use a raw hex value."
            >
                <Row label="Brand" stack>
                    <div className="grid w-full grid-cols-4 gap-4 lg:grid-cols-7">
                        {BRAND.map((swatch) => (
                            <Swatch key={swatch.token} {...swatch} />
                        ))}
                    </div>
                </Row>

                <Row label="Ink & surface" stack>
                    <div className="grid w-full grid-cols-4 gap-4 lg:grid-cols-7">
                        {INK.map((swatch) => (
                            <Swatch key={swatch.token} {...swatch} />
                        ))}
                    </div>
                </Row>

                <Row label="Semantic" stack>
                    <div className="grid w-full grid-cols-4 gap-4 lg:grid-cols-8">
                        {SEMANTIC.map((swatch) => (
                            <Swatch key={swatch.token} {...swatch} />
                        ))}
                    </div>
                </Row>
            </Section>

            <Section
                id="typography"
                title="Typography"
                note="Sora 600/500 for headings and UI, Inter for body and tables. Sentence case everywhere — no all-caps labels, no eyebrow labels above headings."
            >
                <Row label="Scale" stack>
                    <div className="flex w-full flex-col gap-3">
                        {SCALE.map((step) => (
                            <div key={step.size} className="flex items-baseline gap-5 border-b border-subtle pb-3">
                                <code className="num w-12 shrink-0 text-xs text-ink-500">{step.size}</code>
                                <span className={`${step.className} flex-1 font-sora font-semibold text-ink-900`}>
                                    Buy guest posts on vetted sites
                                </span>
                                <span className="shrink-0 text-sm text-ink-500">{step.use}</span>
                            </div>
                        ))}
                    </div>
                </Row>

                <Row label="Tabular figures" stack>
                    <Panel>
                        <p className="mb-3 max-w-xl text-base text-ink-500">
                            The <code className="rounded bg-sunken px-1">.num</code> utility goes on every numeric cell.
                            Without it, digits have different widths and a price column stops lining up.
                        </p>
                        <div className="grid grid-cols-2 gap-8">
                            <div>
                                <p className="mb-1 text-sm text-ink-500">With .num</p>
                                <p className="num text-md text-ink-900">$1,111.00</p>
                                <p className="num text-md text-ink-900">$8,888.00</p>
                                <p className="num text-md text-ink-900">$1,090.50</p>
                            </div>
                            <div>
                                <p className="mb-1 text-sm text-ink-500">Without</p>
                                <p className="text-md text-ink-900">$1,111.00</p>
                                <p className="text-md text-ink-900">$8,888.00</p>
                                <p className="text-md text-ink-900">$1,090.50</p>
                            </div>
                        </div>
                    </Panel>
                </Row>
            </Section>

            <Section id="shape" title="Shape, elevation and motion">
                <Row label="Radius">
                    <div className="flex size-20 items-center justify-center rounded-button border border-subtle bg-card text-sm text-ink-500">
                        6px
                    </div>
                    <div className="flex size-20 items-center justify-center rounded-card border border-subtle bg-card text-sm text-ink-500">
                        8px
                    </div>
                    <div className="flex h-8 items-center justify-center rounded-pill border border-subtle bg-card px-4 text-sm text-ink-500">
                        999px
                    </div>
                </Row>

                <Row label="Elevation">
                    <div className="flex h-20 w-40 items-center justify-center rounded-card bg-card text-sm text-ink-500 shadow-card">
                        The one shadow
                    </div>
                </Row>

                <Row label="Motion" stack>
                    <p className="max-w-2xl text-base text-ink-500">
                        Motion happens only in response to a user action: row expand 150ms, drawer slide 180ms, toast
                        200ms. There are no scroll-triggered entrance animations anywhere in the system, and{' '}
                        <code className="rounded bg-sunken px-1">prefers-reduced-motion</code> flattens every duration
                        to near zero via a single global rule.
                    </p>
                </Row>
            </Section>
        </>
    );
}
