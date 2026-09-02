import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, Checkbox, ChevronDownIcon, Input, MultiSelect, RangeSlider, SearchIcon, Select } from '@shared/ui';
import type { PostFilterState, PostOptions } from '@shared/types/posts';
import { useDebouncedSearch } from './usePostFilters';

interface Props {
    filters: PostFilterState;
    options: PostOptions;
    onChange: (patch: Partial<PostFilterState>) => void;
}

const DEADLINES = [
    { value: '', label: 'Any deadline' },
    { value: '24h', label: 'Within 24 hours' },
    { value: '3d', label: 'Within 3 days' },
    { value: '7d', label: 'Within 7 days' },
    { value: 'overdue', label: 'Overdue' },
];

const CONTENT_MODES = [
    { value: '', label: 'Any content mode' },
    { value: 'advertiser_provides', label: 'I provide the article' },
    { value: 'publisher_writes', label: 'Publisher writes it' },
];

/**
 * One wrapping row of the filters people reach for constantly, and a disclosure
 * for the thirteen they do not.
 *
 * The split is by frequency, not by importance: search, project, status and
 * date carry most sessions, and putting the other thirteen beside them would
 * make the common case hunt through a wall of controls.
 */
export function FilterBar({ filters, options, onChange }: Props) {
    // Open on arrival when an advanced filter is already set — otherwise a
    // shared link would show chips for filters whose controls are hidden.
    const [advanced, setAdvanced] = useState(() => hasAdvanced(filters));

    const [search, setSearch] = useDebouncedSearch(filters.q ?? '', (value) => onChange({ q: value || undefined }));

    const folders = options.projects.flatMap((project) =>
        project.folders.map((folder) => ({ value: String(folder.id), label: `${project.name} / ${folder.name}` })),
    );

    return (
        <div className="rounded-card border border-subtle bg-card p-3 shadow-card">
            <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[240px] flex-1">
                    <Input
                        label="Search"
                        hideLabel
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Domain, anchor, target URL or post ID"
                        leadingIcon={<SearchIcon size={16} />}
                        type="search"
                    />
                </div>

                <MultiSelect
                    label="Projects"
                    hideLabel
                    placeholder="All projects"
                    className="w-52"
                    options={options.projects.map((p) => ({ value: String(p.id), label: p.name }))}
                    value={(filters.projects ?? []).map(String)}
                    onChange={(value) => onChange({ projects: value.map(Number) })}
                />

                <MultiSelect
                    label="Statuses"
                    hideLabel
                    placeholder="All statuses"
                    className="w-52"
                    options={options.statuses.map((s) => ({ value: s.value, label: s.label }))}
                    value={filters.statuses ?? []}
                    onChange={(value) => onChange({ statuses: value })}
                />

                <div className="flex flex-wrap items-end gap-1.5">
                    <Select
                        label="Date field"
                        hideLabel
                        className="w-32"
                        value={filters.date_field ?? 'created'}
                        onChange={(event) => onChange({ date_field: event.target.value as 'created' | 'published' })}
                        options={[
                            { value: 'created', label: 'Created' },
                            { value: 'published', label: 'Published' },
                        ]}
                    />
                    <Input
                        label="From"
                        hideLabel
                        type="date"
                        className="w-36"
                        value={filters.from ?? ''}
                        onChange={(event) => onChange({ from: event.target.value || undefined })}
                    />
                    <span className="pb-2.5 text-sm text-ink-500">to</span>
                    <Input
                        label="To"
                        hideLabel
                        type="date"
                        className="w-36"
                        value={filters.to ?? ''}
                        onChange={(event) => onChange({ to: event.target.value || undefined })}
                    />
                </div>

                <Button
                    variant="secondary"
                    onClick={() => setAdvanced((open) => !open)}
                    aria-expanded={advanced}
                    className="whitespace-nowrap"
                >
                    Advanced
                    <ChevronDownIcon
                        size={14}
                        className={cn('transition-transform duration-fast', advanced && 'rotate-180')}
                    />
                </Button>
            </div>

            {advanced && (
                <div className="mt-3 grid grid-cols-1 gap-3 border-t border-subtle pt-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MultiSelect
                        label="Website category"
                        options={options.categories.map((c) => ({ value: String(c.id), label: c.name }))}
                        value={(filters.categories ?? []).map(String)}
                        onChange={(value) => onChange({ categories: value.map(Number) })}
                    />
                    <MultiSelect
                        label="Website country"
                        options={options.countries.map((c) => ({ value: String(c.id), label: c.name }))}
                        value={(filters.countries ?? []).map(String)}
                        onChange={(value) => onChange({ countries: value.map(Number) })}
                    />
                    <MultiSelect
                        label="Language"
                        options={options.languages.map((l) => ({ value: String(l.id), label: l.name }))}
                        value={(filters.languages ?? []).map(String)}
                        onChange={(value) => onChange({ languages: value.map(Number) })}
                    />
                    <Select
                        label="Content mode"
                        value={filters.content_mode ?? ''}
                        onChange={(event) =>
                            onChange({
                                content_mode: (event.target.value || undefined) as PostFilterState['content_mode'],
                            })
                        }
                        options={CONTENT_MODES}
                    />

                    <RangeSlider
                        label="Price"
                        min={0}
                        max={5000}
                        step={25}
                        showInputs
                        value={[filters.min_price ?? 0, filters.max_price ?? 5000]}
                        format={(value) => `$${value.toLocaleString('en-US')}`}
                        onChange={([min, max]) =>
                            onChange({
                                min_price: min > 0 ? min : undefined,
                                max_price: max < 5000 ? max : undefined,
                            })
                        }
                    />
                    <RangeSlider
                        label="Domain rating"
                        showInputs={false}
                        min={0}
                        max={100}
                        value={[filters.min_dr ?? 0, filters.max_dr ?? 100]}
                        onChange={([min, max]) =>
                            onChange({
                                min_dr: min > 0 ? min : undefined,
                                max_dr: max < 100 ? max : undefined,
                            })
                        }
                    />
                    <RangeSlider
                        label="Monthly traffic"
                        showInputs={false}
                        min={0}
                        max={1_000_000}
                        step={5_000}
                        value={[filters.min_traffic ?? 0, filters.max_traffic ?? 1_000_000]}
                        format={(value) => value.toLocaleString('en-US')}
                        onChange={([min, max]) =>
                            onChange({
                                min_traffic: min > 0 ? min : undefined,
                                max_traffic: max < 1_000_000 ? max : undefined,
                            })
                        }
                    />
                    <Select
                        label="Folder"
                        value={filters.folder ? String(filters.folder) : ''}
                        onChange={(event) => onChange({ folder: Number(event.target.value) || undefined })}
                        options={[{ value: '', label: 'Any folder' }, ...folders]}
                    />

                    <Input
                        label="Anchor text contains"
                        value={filters.anchor ?? ''}
                        onChange={(event) => onChange({ anchor: event.target.value || undefined })}
                    />
                    <Input
                        label="Target URL contains"
                        value={filters.target ?? ''}
                        onChange={(event) => onChange({ target: event.target.value || undefined })}
                    />
                    <Select
                        label="Deadline within"
                        value={filters.deadline ?? ''}
                        onChange={(event) =>
                            onChange({ deadline: (event.target.value || undefined) as PostFilterState['deadline'] })
                        }
                        options={DEADLINES}
                    />

                    <div className="flex items-end pb-2.5">
                        <Checkbox
                            label="Has unread messages"
                            checked={filters.unread === 1}
                            onChange={(event) => onChange({ unread: event.target.checked ? 1 : undefined })}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

const ADVANCED_KEYS: (keyof PostFilterState)[] = [
    'categories',
    'countries',
    'languages',
    'min_price',
    'max_price',
    'content_mode',
    'anchor',
    'target',
    'min_dr',
    'max_dr',
    'min_traffic',
    'max_traffic',
    'folder',
    'unread',
    'deadline',
];

function hasAdvanced(filters: PostFilterState): boolean {
    return ADVANCED_KEYS.some((key) => {
        const value = filters[key];

        return Array.isArray(value) ? value.length > 0 : value !== undefined;
    });
}
