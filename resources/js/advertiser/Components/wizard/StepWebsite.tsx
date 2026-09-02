import { useRef, useState } from 'react';
import { Combobox, Input } from '@shared/ui';
import type { SitePreview, WizardOptions, WizardState } from '@shared/types/wizard';
import { ColorSwatchPicker } from './ColorSwatchPicker';
import { SitePreviewCard } from './SitePreviewCard';
import { hostOf } from './validation';

interface Props {
    state: WizardState;
    options: WizardOptions;
    errors: Record<string, string>;
    onChange: (changes: Partial<WizardState>) => void;
}

export function StepWebsite({ state, options, errors, onChange }: Props) {
    const [loading, setLoading] = useState(false);
    const [touched, setTouched] = useState<Record<string, boolean>>({});
    const lastFetched = useRef<string | null>(null);
    // Once someone picks a swatch the suggestion stops overriding it.
    const colorTouched = useRef(false);

    /**
     * The preview is fetched on blur, not on every keystroke: it costs a real
     * outbound request, and a half-typed domain is never the one meant.
     */
    function fetchPreview() {
        const url = state.website_url.trim();

        if (url === '' || hostOf(url) === null || url === lastFetched.current) return;

        lastFetched.current = url;
        setLoading(true);

        void fetch('/projects/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ url }),
        })
            .then((response) => (response.ok ? (response.json() as Promise<SitePreview>) : null))
            .then((preview) => {
                if (preview === null) return;

                // The title seeds the name only while the name is untouched,
                // so a fetch never overwrites something already typed.
                const shouldSeedName = state.name.trim() === '' && typeof preview.title === 'string';
                const shouldSeedColor = !colorTouched.current && typeof preview.suggested_color === 'string';

                onChange({
                    preview,
                    ...(shouldSeedName ? { name: (preview.title ?? '').slice(0, 60) } : {}),
                    ...(shouldSeedColor ? { color: preview.suggested_color } : {}),
                });
            })
            .catch(() => onChange({ preview: { ok: false, reason: 'We could not reach that site.' } }))
            .finally(() => setLoading(false));
    }

    const urlError =
        touched.website_url && state.website_url.trim() !== '' && hostOf(state.website_url) === null
            ? 'Enter a web address, like example.com or https://example.com.'
            : errors.website_url;

    return (
        <div className="flex flex-col gap-5">
            <div>
                <Input
                    label="Website you are promoting"
                    type="url"
                    inputMode="url"
                    value={state.website_url}
                    onChange={(event) => onChange({ website_url: event.target.value })}
                    onBlur={() => {
                        setTouched((t) => ({ ...t, website_url: true }));
                        fetchPreview();
                    }}
                    error={urlError}
                    placeholder="example.com"
                    hint="We will read the page to check you have the right site."
                    required
                />

                <SitePreviewCard preview={state.preview} loading={loading} />
            </div>

            <Input
                label="Project name"
                value={state.name}
                onChange={(event) => onChange({ name: event.target.value.slice(0, 60) })}
                onBlur={() => setTouched((t) => ({ ...t, name: true }))}
                error={
                    touched.name && state.name.trim() === ''
                        ? 'Give the project a name so you can find it later.'
                        : errors.name
                }
                maxLength={60}
                hint={`${state.name.length}/60 — defaults to the site's title, change it to whatever you will recognise.`}
                required
            />

            <Combobox
                label="Category"
                value={state.category_id === '' ? null : state.category_id}
                onChange={(value) => onChange({ category_id: value ?? '' })}
                options={options.categories.map((category) => ({
                    value: String(category.id),
                    label: category.name,
                }))}
                placeholder="Search categories…"
                error={errors.category_id}
                hint="What the site is about. We use it to suggest places that fit."
                required
            />

            <ColorSwatchPicker colors={options.colors} value={state.color} onChange={(color) => onChange({ color })} />
        </div>
    );
}
