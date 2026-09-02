import { cn } from '@shared/lib/cn';
import { MultiSelect } from '@shared/ui';
import type { WizardOptions, WizardState } from '@shared/types/wizard';
import { RichBriefEditor } from './RichBriefEditor';

interface Props {
    state: WizardState;
    options: WizardOptions;
    errors: Record<string, string>;
    onChange: (changes: Partial<WizardState>) => void;
}

/** A flag from the ISO code, without shipping an image set. */
function flag(code: string): string {
    return code.toUpperCase().replace(/./g, (char) => String.fromCodePoint(127_397 + char.charCodeAt(0)));
}

export function StepTargeting({ state, options, errors, onChange }: Props) {
    const allCountries = options.countries.map((country) => country.id);
    const allSelected = state.country_ids.length === allCountries.length && allCountries.length > 0;

    return (
        <div className="flex flex-col gap-6">
            <fieldset>
                <legend className="text-sm font-medium text-ink-700">Sensitive topics</legend>
                <p className="mt-0.5 text-sm text-ink-500">
                    Pick the ones your content covers. It flags the sites that accept the topic — most do not, so
                    leaving these blank shows you the widest choice.
                </p>

                <div className="mt-2.5 flex flex-wrap gap-2">
                    {options.topics.map((topic) => {
                        const selected = state.sensitive_topic_ids.includes(topic.id);

                        return (
                            <button
                                key={topic.id}
                                type="button"
                                aria-pressed={selected}
                                onClick={() =>
                                    onChange({
                                        sensitive_topic_ids: selected
                                            ? state.sensitive_topic_ids.filter((id) => id !== topic.id)
                                            : [...state.sensitive_topic_ids, topic.id],
                                    })
                                }
                                className={cn(
                                    'rounded-pill border px-3 py-1.5 text-sm font-medium',
                                    'transition-colors duration-fast ease-standard',
                                    selected
                                        ? 'border-brand bg-brand-subtle text-brand'
                                        : 'border-subtle bg-card text-ink-700 hover:bg-sunken',
                                )}
                            >
                                {topic.name}
                            </button>
                        );
                    })}
                </div>

                {state.sensitive_topic_ids.length > 0 && (
                    <p className="mt-2.5 rounded-card bg-warning-bg px-3 py-2 text-sm text-warning">
                        Sites that accept these topics usually charge more, and there are fewer of them. Nothing is
                        blocked — expect a smaller catalog at higher prices.
                    </p>
                )}
            </fieldset>

            <div>
                <div className="flex items-end justify-between gap-3">
                    <span className="text-sm font-medium text-ink-700">Countries</span>
                    <button
                        type="button"
                        onClick={() => onChange({ country_ids: allSelected ? [] : allCountries })}
                        className="text-sm font-medium text-brand hover:underline"
                    >
                        {allSelected ? 'Clear all' : 'Select all'}
                    </button>
                </div>

                <MultiSelect
                    label="Countries"
                    hideLabel
                    className="mt-1.5"
                    placeholder="Any country"
                    options={options.countries.map((country) => ({
                        value: String(country.id),
                        label: `${flag(country.code)}  ${country.name}`,
                    }))}
                    value={state.country_ids.map(String)}
                    onChange={(value) => onChange({ country_ids: value.map(Number) })}
                    hint="Where the audience is. Leave blank for anywhere."
                    error={errors.country_ids}
                />
            </div>

            <MultiSelect
                label="Languages"
                placeholder="Any language"
                options={options.languages.map((language) => ({
                    value: String(language.id),
                    label: language.name,
                }))}
                value={state.language_ids.map(String)}
                onChange={(value) => onChange({ language_ids: value.map(Number) })}
                hint="What the article should be written in. Leave blank for any."
                error={errors.language_ids}
            />

            <RichBriefEditor
                value={state.publisher_task}
                onChange={(html) => onChange({ publisher_task: html })}
                error={errors.publisher_task}
            />
        </div>
    );
}
