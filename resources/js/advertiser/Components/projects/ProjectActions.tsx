import { router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { Tooltip, TrashIcon } from '@shared/ui';
import type { ProjectRow } from '@shared/types/projects';

interface Props {
    project: ProjectRow;
    onDelete: (project: ProjectRow) => void;
}

/**
 * Edit, Archive and Delete — or just Restore, once a project is archived.
 *
 * An archived project offers one action deliberately: it is finished with, and
 * the way back to editing it is to restore it first. Offering Edit on
 * something the policy will refuse would be a button that does nothing.
 */
export function ProjectActions({ project, onDelete }: Props) {
    if (project.isArchived) {
        return (
            <span className="flex items-center gap-1" onClick={(event) => event.stopPropagation()}>
                <ActionButton
                    label="Restore project"
                    onClick={() => router.post(`/projects/${project.id}/restore`, {}, { preserveScroll: true })}
                >
                    <RestoreIcon />
                </ActionButton>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1" onClick={(event) => event.stopPropagation()}>
            <ActionButton
                label="Edit project settings"
                onClick={() => router.visit(`/projects/${project.id}?tab=settings`)}
            >
                <PencilIcon />
            </ActionButton>

            <ActionButton
                label="Archive project"
                onClick={() => router.post(`/projects/${project.id}/archive`, {}, { preserveScroll: true })}
            >
                <BoxIcon />
            </ActionButton>

            <ActionButton label="Delete project" destructive onClick={() => onDelete(project)}>
                <TrashIcon size={15} />
            </ActionButton>
        </span>
    );
}

function ActionButton({
    label,
    destructive = false,
    onClick,
    children,
}: {
    label: string;
    destructive?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Tooltip content={label}>
            <button
                type="button"
                aria-label={label}
                onClick={onClick}
                className={cn(
                    'rounded-button p-1.5 text-ink-500 transition-colors duration-fast ease-standard',
                    destructive ? 'hover:bg-danger-bg hover:text-danger' : 'hover:bg-sunken hover:text-ink-900',
                )}
            >
                {children}
            </button>
        </Tooltip>
    );
}

function Icon({ children }: { children: React.ReactNode }) {
    return (
        <svg
            width={15}
            height={15}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

function PencilIcon() {
    return (
        <Icon>
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </Icon>
    );
}

function BoxIcon() {
    return (
        <Icon>
            <path d="M21 8v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8" />
            <path d="M1 4h22v4H1z" />
            <path d="M10 12h4" />
        </Icon>
    );
}

function RestoreIcon() {
    return (
        <Icon>
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5" />
        </Icon>
    );
}
