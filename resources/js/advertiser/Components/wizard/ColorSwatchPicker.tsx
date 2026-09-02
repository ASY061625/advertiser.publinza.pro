import { cn } from '@shared/lib/cn';
import { CheckIcon } from '@shared/ui';

interface Props {
    colors: string[];
    value: string;
    onChange: (color: string) => void;
}

/**
 * The dot that identifies the project in lists.
 *
 * The tick, not the ring, is what says "chosen": a ring around a coloured
 * circle is a second colour cue, and this is the one control on the page whose
 * whole content is colour.
 */
export function ColorSwatchPicker({ colors, value, onChange }: Props) {
    return (
        <fieldset>
            <legend className="text-sm font-medium text-ink-700">Project colour</legend>
            <p className="mt-0.5 text-sm text-ink-500">
                The dot beside this project in your lists. Suggested from the domain.
            </p>

            <div className="mt-2 flex flex-wrap gap-2">
                {colors.map((color) => {
                    const selected = color === value;

                    return (
                        <button
                            key={color}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            aria-label={`Colour ${color}`}
                            onClick={() => onChange(color)}
                            style={{ backgroundColor: color }}
                            className={cn(
                                'flex size-8 items-center justify-center rounded-pill text-white',
                                'transition-transform duration-fast ease-standard hover:scale-110',
                                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                            )}
                        >
                            {selected && <CheckIcon size={16} />}
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}
