import { useState } from 'react';
import {
    Checkbox,
    Combobox,
    DatePicker,
    DateRangePicker,
    Input,
    MultiSelect,
    NumberInput,
    RadioGroup,
    RangeSlider,
    SearchIcon,
    Select,
    Switch,
    Textarea,
    type DateRange,
    type IsoDate,
} from '@shared/ui';
import { Row, Section } from './Shell';

const CATEGORIES = [
    { value: 'technology', label: 'Technology' },
    { value: 'finance', label: 'Finance' },
    { value: 'health', label: 'Health' },
    { value: 'travel', label: 'Travel' },
    { value: 'marketing', label: 'Marketing' },
    { value: 'legal', label: 'Legal', disabled: true },
];

const SITES = [
    { value: 'techcrunch', label: 'techcrunch.com', meta: 'Technology · DR 91' },
    { value: 'wired', label: 'wired.com', meta: 'Technology · DR 89' },
    { value: 'forbes', label: 'forbes.com', meta: 'Finance · DR 94' },
    { value: 'healthline', label: 'healthline.com', meta: 'Health · DR 87' },
];

export function FormsSection() {
    const [text, setText] = useState('');
    const [amount, setAmount] = useState<number | ''>(250);
    const [category, setCategory] = useState('technology');
    const [categories, setCategories] = useState<string[]>(['technology', 'finance']);
    const [site, setSite] = useState<string | null>('wired');
    const [range, setRange] = useState<[number, number]>([5000, 180000]);
    const [checked, setChecked] = useState(true);
    const [radio, setRadio] = useState('anchor');
    const [enabled, setEnabled] = useState(true);
    const [date, setDate] = useState<IsoDate | null>('2026-09-15');
    const [dateRange, setDateRange] = useState<DateRange>({ start: '2026-09-01', end: '2026-09-30' });

    return (
        <>
            <Section
                id="input"
                title="Input"
                note="Every control shares one frame: label, optional hint, and an error that replaces the hint rather than stacking under it."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <Input
                            label="Target URL"
                            placeholder="https://example.com/page"
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                        />
                        <Input
                            label="Anchor text"
                            hint="The words the link will be wrapped in."
                            defaultValue="best crm software"
                        />
                        <Input
                            label="Email"
                            error="That address is already in use. Sign in instead."
                            defaultValue="rae@publinza.pro"
                        />
                        <Input label="Account ID" disabled defaultValue="acct_9f2b" />
                        <Input label="Search sites" leadingIcon={<SearchIcon size={15} />} placeholder="Search" />
                        <Input label="Required field" required placeholder="Cannot be blank" />
                    </div>
                </Row>
            </Section>

            <Section
                id="number-input"
                title="NumberInput"
                note="Steppers clamp to min/max on every change, so they can never walk out of range. Digits are tabular."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <NumberInput
                            label="Budget"
                            unit="$"
                            value={amount}
                            onValueChange={setAmount}
                            min={10}
                            max={100000}
                            step={10}
                            hint="Minimum $10."
                        />
                        <NumberInput label="At maximum" value={100} min={0} max={100} onValueChange={() => undefined} />
                        <NumberInput label="Disabled" value={50} disabled onValueChange={() => undefined} />
                        <NumberInput
                            label="With error"
                            value={5}
                            error="The smallest top-up is $10."
                            onValueChange={() => undefined}
                        />
                    </div>
                </Row>
            </Section>

            <Section id="textarea" title="Textarea">
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <Textarea
                            label="Brief"
                            hint="What the writer needs to know."
                            placeholder="Tone, angle, must-mention products…"
                        />
                        <Textarea
                            label="With counter"
                            maxLength={280}
                            showCount
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                        />
                        <Textarea label="Disabled" disabled defaultValue="Locked while the order is in progress." />
                        <Textarea label="With error" error="Add a brief before publishing." />
                    </div>
                </Row>
            </Section>

            <Section
                id="select"
                title="Select"
                note="A native select — it gets keyboard behaviour, mobile pickers and screen-reader support for free. Combobox is the searchable alternative."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <Select
                            label="Category"
                            options={CATEGORIES}
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                        />
                        <Select
                            label="With placeholder"
                            options={CATEGORIES}
                            value=""
                            placeholder="Choose a category"
                            onChange={() => undefined}
                        />
                        <Select
                            label="Disabled"
                            options={CATEGORIES}
                            value="finance"
                            disabled
                            onChange={() => undefined}
                        />
                        <Select
                            label="With error"
                            options={CATEGORIES}
                            value=""
                            error="Pick a category to continue."
                            onChange={() => undefined}
                        />
                    </div>
                </Row>
            </Section>

            <Section
                id="multiselect"
                title="MultiSelect"
                note="Search inside the popover, chips outside it. Backspace on an empty search removes the last chip."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <MultiSelect
                            label="Categories"
                            options={CATEGORIES}
                            value={categories}
                            onChange={setCategories}
                        />
                        <MultiSelect
                            label="Empty"
                            options={CATEGORIES}
                            value={[]}
                            onChange={() => undefined}
                            placeholder="Any category"
                        />
                        <MultiSelect
                            label="Overflowing"
                            options={CATEGORIES}
                            value={['technology', 'finance', 'health', 'travel']}
                            onChange={() => undefined}
                        />
                        <MultiSelect
                            label="Disabled"
                            options={CATEGORIES}
                            value={['health']}
                            disabled
                            onChange={() => undefined}
                        />
                    </div>
                </Row>
            </Section>

            <Section
                id="combobox"
                title="Combobox"
                note="Single-select with type-to-filter, following the ARIA combobox pattern."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <Combobox label="Site" options={SITES} value={site} onChange={setSite} />
                        <Combobox label="Loading" options={[]} value={null} loading onChange={() => undefined} />
                        <Combobox label="Disabled" options={SITES} value="forbes" disabled onChange={() => undefined} />
                        <Combobox
                            label="With error"
                            options={SITES}
                            value={null}
                            error="Pick a site to continue."
                            onChange={() => undefined}
                        />
                    </div>
                </Row>
            </Section>

            <Section
                id="range-slider"
                title="RangeSlider"
                note="Two native range inputs stacked on one track, clamped against each other so the handles cannot cross."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-8">
                        <RangeSlider
                            label="Traffic"
                            min={0}
                            max={500000}
                            step={1000}
                            value={range}
                            onChange={setRange}
                            format={(v) => `${(v / 1000).toFixed(0)}k`}
                        />
                        <RangeSlider
                            label="Disabled"
                            min={0}
                            max={100}
                            value={[20, 80]}
                            disabled
                            showInputs={false}
                            onChange={() => undefined}
                        />
                    </div>
                </Row>
            </Section>

            <Section id="choice" title="Checkbox, Radio and Switch">
                <Row label="Checkbox" stack>
                    <Checkbox label="Only show sites I have not used" defaultChecked />
                    <Checkbox
                        label="Indeterminate (header state)"
                        indeterminate
                        onChange={() => undefined}
                        checked={false}
                    />
                    <Checkbox label="Disabled" disabled />
                    <Checkbox label="Disabled and checked" disabled defaultChecked />
                    <Checkbox label="With error" error="Accept the terms to continue." />
                    <Checkbox
                        label="With hint"
                        hint="We will email you when the post goes live."
                        checked={checked}
                        onChange={(e) => setChecked(e.target.checked)}
                    />
                </Row>

                <Row label="Radio" stack>
                    <RadioGroup
                        legend="Link type"
                        value={radio}
                        onChange={setRadio}
                        options={[
                            { value: 'anchor', label: 'Anchor text', hint: 'The link is wrapped in chosen words.' },
                            { value: 'brand', label: 'Brand mention' },
                            { value: 'naked', label: 'Naked URL', disabled: true },
                        ]}
                    />
                </Row>

                <Row label="Switch" stack>
                    <div className="w-full max-w-sm space-y-4">
                        <Switch
                            label="Email me on publication"
                            hint="Takes effect immediately."
                            checked={enabled}
                            onCheckedChange={setEnabled}
                        />
                        <Switch label="Off" checked={false} onCheckedChange={() => undefined} />
                        <Switch label="Disabled" checked disabled onCheckedChange={() => undefined} />
                        <Switch label="Saving" checked loading onCheckedChange={() => undefined} />
                    </div>
                </Row>
            </Section>

            <Section
                id="dates"
                title="DatePicker and DateRangePicker"
                note="Dates move through the system as YYYY-MM-DD strings — a calendar day has no time and no zone."
            >
                <Row label="States" stack>
                    <div className="grid w-full max-w-3xl grid-cols-2 gap-5">
                        <DatePicker label="Publish on" value={date} onChange={setDate} min="2026-01-01" />
                        <DatePicker label="Empty" value={null} onChange={() => undefined} />
                        <DatePicker label="Disabled" value="2026-09-15" disabled onChange={() => undefined} />
                        <DatePicker
                            label="With error"
                            value={null}
                            error="Pick a date in the future."
                            onChange={() => undefined}
                        />
                        <DateRangePicker label="Reporting period" value={dateRange} onChange={setDateRange} />
                        <DateRangePicker label="Empty" value={{ start: null, end: null }} onChange={() => undefined} />
                    </div>
                </Row>
            </Section>
        </>
    );
}
