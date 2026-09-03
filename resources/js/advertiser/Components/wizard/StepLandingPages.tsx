import type { WizardState } from '@shared/types/wizard';
import { LandingPageEditor } from '../projects/LandingPageEditor';
import { hostOf, registrable } from './validation';

interface Props {
    state: WizardState;
    errors: Record<string, string>;
    onChange: (changes: Partial<WizardState>) => void;
}

/**
 * The wizard's third step is the landing-page editor with the wizard's wording
 * around it. The widget itself lives in Components/projects because the folder
 * editor is the same list — a landing page validated one way here and another
 * way there would be two different products.
 */
export function StepLandingPages({ state, errors, onChange }: Props) {
    const promoted = registrable(hostOf(state.website_url) ?? '') ?? 'your site';

    return (
        <LandingPageEditor
            rows={state.landing_pages}
            onChange={(landing_pages) => onChange({ landing_pages })}
            websiteUrl={state.website_url}
            errors={errors}
            // A project without a landing page has nowhere for its links to
            // point, so the wizard never lets the list empty out.
            minRows={1}
            // The step card already carries the "Landing pages" heading, so
            // this is the one line the shared widget's default would repeat.
            heading={
                <p className="text-sm text-ink-500">
                    The pages on {promoted} that links will point to. Drag to reorder.
                </p>
            }
        />
    );
}
