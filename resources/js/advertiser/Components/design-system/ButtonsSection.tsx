import {
    Avatar,
    Badge,
    Breadcrumb,
    Button,
    DownloadIcon,
    IconButton,
    PlusIcon,
    STATUS_KEYS,
    Tooltip,
    TrashIcon,
    type ButtonVariant,
} from '@shared/ui';
import { Row, Section } from './Shell';

const VARIANTS: ButtonVariant[] = ['primary', 'secondary', 'ghost', 'danger'];

export function ButtonsSection() {
    return (
        <>
            <Section
                id="button"
                title="Button"
                note="Buttons name the outcome — “Add to cart”, “Publish project”. Never a bare “Submit”, and never an arrow character glued to the label."
            >
                {VARIANTS.map((variant) => (
                    <Row key={variant} label={variant}>
                        {/* Hover, focus-visible and active are live states — hover
                            and tab through these to see them. */}
                        <Button variant={variant}>Add to cart</Button>
                        <Button variant={variant} disabled>
                            Disabled
                        </Button>
                        <Button variant={variant} loading>
                            Publishing
                        </Button>
                        <Button variant={variant} error>
                            Error
                        </Button>
                    </Row>
                ))}

                <Row label="Sizes">
                    <Button size="sm">Small</Button>
                    <Button size="md">Medium</Button>
                    <Button size="lg">Large</Button>
                </Row>

                <Row label="With icons">
                    <Button leadingIcon={<PlusIcon size={15} />}>New project</Button>
                    <Button variant="secondary" trailingIcon={<DownloadIcon size={15} />}>
                        Export CSV
                    </Button>
                </Row>

                <Row label="Focus ring">
                    <p className="max-w-xl text-base text-ink-500">
                        Tab through this page: every interactive element takes the same 2px brand-blue ring at 2px
                        offset, set once globally on <code className="rounded bg-sunken px-1">:focus-visible</code>{' '}
                        rather than per component.
                    </p>
                </Row>
            </Section>

            <Section
                id="icon-button"
                title="IconButton"
                note="An icon-only control always carries a label prop — it becomes both the accessible name and the tooltip."
            >
                <Row label="Variants">
                    <IconButton label="Add" icon={<PlusIcon size={16} />} variant="primary" />
                    <IconButton label="Add" icon={<PlusIcon size={16} />} variant="secondary" />
                    <IconButton label="Add" icon={<PlusIcon size={16} />} variant="ghost" />
                    <IconButton label="Delete" icon={<TrashIcon size={16} />} variant="danger" />
                </Row>
                <Row label="States">
                    <IconButton label="Add" icon={<PlusIcon size={16} />} variant="secondary" disabled />
                    <IconButton label="Saving" icon={<PlusIcon size={16} />} variant="secondary" loading />
                </Row>
                <Row label="Sizes">
                    <IconButton label="Add" icon={<PlusIcon size={14} />} variant="secondary" size="sm" />
                    <IconButton label="Add" icon={<PlusIcon size={16} />} variant="secondary" size="md" />
                    <IconButton label="Add" icon={<PlusIcon size={18} />} variant="secondary" size="lg" />
                </Row>
            </Section>

            <Section
                id="badge"
                title="Badge"
                note="The status vocabulary is fixed product-wide. A “Posted” chip is the same chip in the catalog, the project drawer and the admin order table."
            >
                <Row label="All statuses">
                    {STATUS_KEYS.map((status) => (
                        <Badge key={status} status={status} />
                    ))}
                </Row>
            </Section>

            <Section id="avatar" title="Avatar">
                <Row label="Sizes">
                    <Avatar name="Rae Whitfield" size="sm" />
                    <Avatar name="Rae Whitfield" size="md" />
                    <Avatar name="Rae Whitfield" size="lg" />
                </Row>
                <Row label="Fallback">
                    <Avatar name="Jordan Alvarez" />
                    <Avatar name="Sam" />
                    <Avatar name="Broken image" src="https://example.invalid/missing.png" />
                </Row>
            </Section>

            <Section
                id="tooltip"
                title="Tooltip"
                note="Opens on hover and on focus, so it is reachable from the keyboard."
            >
                <Row label="Sides">
                    <Tooltip content="Spam score is inverted: lower is better.">
                        <Button variant="secondary">Hover or focus me</Button>
                    </Tooltip>
                    <Tooltip content="Opens below instead." side="bottom">
                        <Button variant="secondary">Bottom</Button>
                    </Tooltip>
                </Row>
            </Section>

            <Section id="breadcrumb" title="Breadcrumb" note="The last crumb is the current page and is never a link.">
                <Row label="Default">
                    <Breadcrumb
                        items={[
                            { label: 'Projects', href: '/projects' },
                            { label: 'Q3 link building', href: '/projects/12' },
                            { label: 'techcrunch.com' },
                        ]}
                    />
                </Row>
            </Section>
        </>
    );
}
