import { useEffect, useRef } from 'react';
import { cn } from '@shared/lib/cn';
import { Dropdown } from '@shared/ui';
import { MAX_TASK_CHARS, taskLength } from './validation';

interface Props {
    value: string;
    onChange: (html: string) => void;
    error?: string;
    /** Overridden by the folder editor, where the brief is the folder's. */
    label?: string;
    hint?: string;
    /**
     * Renders a "Copy from project" link beside the template menu. Given only
     * where there is a project-level brief to copy — an action that would do
     * nothing is worse than no action at all.
     */
    onCopyFromProject?: () => void;
    /** Unique when two briefs could appear on one page. */
    id?: string;
}

/**
 * Bold, italic, lists and links — nothing else.
 *
 * A contenteditable produces whatever markup the browser feels like, and
 * pasting from a word processor brings a payload of spans, styles and comments
 * with it. So two things are true here: paste is intercepted and inserted as
 * plain text, and the server sanitises the result again on the way in. The
 * toolbar restricting the buttons is a convenience, not the control — the
 * control is App\Support\HtmlSanitizer.
 */
const TEMPLATES: { id: string; label: string; html: string }[] = [
    {
        id: 'review',
        label: 'Product review',
        html:
            '<p>Please write an honest review of our product.</p>' +
            '<ul><li>Cover what it does and who it is for</li>' +
            '<li>Mention pricing in general terms, not exact figures</li>' +
            '<li>Say what it is <strong>not</strong> good at — a review with no criticism reads as an advert</li></ul>' +
            '<p>Link naturally from the body, not from a call-to-action block.</p>',
    },
    {
        id: 'informational',
        label: 'Informational article',
        html:
            '<p>Write a genuinely useful article on the topic, with our link as one source among several.</p>' +
            '<ul><li>Lead with the answer, then the detail</li>' +
            '<li>No superlatives about us</li>' +
            '<li>Link once, in the paragraph where it is actually relevant</li></ul>',
    },
    {
        id: 'listicle',
        label: 'Listicle mention',
        html:
            '<p>Include us in a round-up of options in this category.</p>' +
            '<ul><li>Place us mid-list, not first — first reads as paid</li>' +
            '<li>Give each entry the same treatment and length</li>' +
            '<li>One sentence on what makes us different is enough</li></ul>',
    },
];

export function RichBriefEditor({
    value,
    onChange,
    error,
    label = 'Task for the publisher',
    hint = 'Tone, things to avoid, anything a writer should know. Bold, italic, lists and links only.',
    onCopyFromProject,
    id = 'publisher-task',
}: Props) {
    const ref = useRef<HTMLDivElement>(null);

    // Written into the DOM only when the two have genuinely diverged. Setting
    // innerHTML on every render would move the caret to the start on every
    // keystroke, which is the classic contenteditable bug.
    useEffect(() => {
        if (ref.current && ref.current.innerHTML !== value) {
            ref.current.innerHTML = value;
        }
    }, [value]);

    const used = taskLength(value);
    const over = used > MAX_TASK_CHARS;

    function exec(command: string) {
        ref.current?.focus();
        document.execCommand(command);
        onChange(ref.current?.innerHTML ?? '');
    }

    function addLink() {
        const href = window.prompt('Link to which address?');
        if (href === null || href.trim() === '') return;

        // Only http(s). A javascript: URL typed here would be stripped by the
        // server anyway, but there is no reason to let it into the document.
        if (!/^https?:\/\//i.test(href.trim())) {
            window.alert('Links need to start with http:// or https://.');

            return;
        }

        ref.current?.focus();
        document.execCommand('createLink', false, href.trim());
        onChange(ref.current?.innerHTML ?? '');
    }

    return (
        <div>
            <div className="flex flex-wrap items-end justify-between gap-2">
                <label htmlFor={id} className="text-sm font-medium text-ink-700">
                    {label}
                </label>

                <span className="flex items-center gap-3">
                    {onCopyFromProject && (
                        <button
                            type="button"
                            onClick={onCopyFromProject}
                            className="rounded-button px-2 py-1 text-sm font-medium text-brand hover:bg-brand-subtle"
                        >
                            Copy from project
                        </button>
                    )}

                    <Dropdown
                        align="end"
                        items={TEMPLATES.map((template) => ({
                            id: template.id,
                            label: template.label,
                            onSelect: () => onChange(template.html),
                        }))}
                        trigger={
                            <button
                                type="button"
                                className="rounded-button px-2 py-1 text-sm font-medium text-brand hover:bg-brand-subtle"
                            >
                                Use a template
                            </button>
                        }
                    />
                </span>
            </div>

            <div className="mt-1.5 overflow-hidden rounded-input border border-subtle bg-card focus-within:border-brand">
                <div className="flex items-center gap-0.5 border-b border-subtle px-1.5 py-1">
                    <ToolbarButton label="Bold" onClick={() => exec('bold')}>
                        <span className="font-bold">B</span>
                    </ToolbarButton>
                    <ToolbarButton label="Italic" onClick={() => exec('italic')}>
                        <span className="italic">I</span>
                    </ToolbarButton>
                    <ToolbarButton label="Bulleted list" onClick={() => exec('insertUnorderedList')}>
                        ••
                    </ToolbarButton>
                    <ToolbarButton label="Numbered list" onClick={() => exec('insertOrderedList')}>
                        1.
                    </ToolbarButton>
                    <ToolbarButton label="Add a link" onClick={addLink}>
                        <span className="underline">link</span>
                    </ToolbarButton>
                </div>

                <div
                    id={id}
                    ref={ref}
                    role="textbox"
                    aria-multiline="true"
                    aria-label={label}
                    contentEditable
                    suppressContentEditableWarning
                    onInput={(event) => onChange(event.currentTarget.innerHTML)}
                    onPaste={(event) => {
                        // Plain text only. A paste from a word processor
                        // otherwise arrives as a wall of styled spans.
                        event.preventDefault();
                        const text = event.clipboardData.getData('text/plain');
                        document.execCommand('insertText', false, text);
                    }}
                    className="prose-publinza min-h-[9rem] max-w-none px-3 py-2.5 text-base text-ink-900 focus:outline-none"
                />
            </div>

            <div className="mt-1.5 flex items-start justify-between gap-3">
                <p className="text-sm text-ink-500">{hint}</p>
                <span className={cn('num shrink-0 text-sm', over ? 'font-medium text-danger' : 'text-ink-500')}>
                    {used.toLocaleString('en-US')}/{MAX_TASK_CHARS.toLocaleString('en-US')}
                </span>
            </div>

            {error && (
                <p role="alert" className="mt-1 text-sm text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}

function ToolbarButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            // Keeps the selection: focus would otherwise leave the editable and
            // execCommand would have nothing to act on.
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
            className="min-w-8 rounded-button px-2 py-1 text-sm text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-900"
        >
            {children}
        </button>
    );
}
