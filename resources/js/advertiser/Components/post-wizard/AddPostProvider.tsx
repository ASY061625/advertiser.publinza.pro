import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { AddPostModal } from './AddPostModal';

interface AddPostValue {
    /** Opens the wizard. Pass a project id to lock step 1 to that project. */
    open: (options?: { projectId?: number | null; resume?: boolean }) => void;
}

const AddPostContext = createContext<AddPostValue | null>(null);

/**
 * One wizard, mounted once, opened from anywhere.
 *
 * The dashboard, the post manager, a project's posts tab and the sidebar all
 * launch the same flow. Four copies of the modal would be four places for the
 * steps to drift apart, and a modal that lives inside a page cannot be opened
 * from the sidebar at all.
 *
 * It is deliberately not a route. The wizard is a side errand: somebody looking
 * at their posts who wants one more should end up back at their posts, not on a
 * URL they now have to navigate out of.
 */
export function AddPostProvider({ children }: { children: ReactNode }) {
    const [state, setState] = useState<{ open: boolean; projectId: number | null; resume: boolean }>({
        open: false,
        projectId: null,
        resume: false,
    });

    const open = useCallback((options?: { projectId?: number | null; resume?: boolean }) => {
        setState({
            open: true,
            projectId: options?.projectId ?? null,
            resume: options?.resume ?? false,
        });
    }, []);

    const value = useMemo(() => ({ open }), [open]);

    return (
        <AddPostContext.Provider value={value}>
            {children}

            {/* Mounted only while open, and keyed on the launch context.
                Rendering it closed would leave its autosave interval running
                over spent state, and reopening for a different project would
                resume the last one's answers under a new heading. */}
            {state.open && (
                <AddPostModal
                    key={`${state.projectId ?? 'none'}-${state.resume}`}
                    open
                    projectId={state.projectId}
                    resume={state.resume}
                    onClose={() => setState((current) => ({ ...current, open: false }))}
                />
            )}
        </AddPostContext.Provider>
    );
}

/**
 * The launcher.
 *
 * Returns a no-op outside the provider rather than throwing: the design-system
 * preview renders components in isolation, and a page that cannot render
 * because a button has nowhere to send its click is worse than a button that
 * does nothing there.
 */
export function useAddPost(): AddPostValue {
    return useContext(AddPostContext) ?? { open: () => undefined };
}
