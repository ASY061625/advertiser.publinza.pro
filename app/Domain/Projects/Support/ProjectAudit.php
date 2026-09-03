<?php

declare(strict_types=1);

namespace App\Domain\Projects\Support;

use App\Domain\Posts\Enums\ActorType;
use App\Domain\Projects\Models\Project;
use App\Domain\System\Models\AuditLog;
use App\Models\User;

/**
 * One audit row per field that actually changed.
 *
 * Per field rather than per save, because the History tab reads as a list of
 * things that happened: "Category changed from Finance to Technology" is a
 * sentence, and one row holding a diff of eleven columns is a JSON blob
 * somebody has to decode before they can read their own history.
 *
 * Values are stored as the label a person would recognise, not the id — a
 * history entry that says "category_id: 3 → 7" is a database record, not an
 * answer to "what did I change last Tuesday".
 */
final class ProjectAudit
{
    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  field => [before, after]
     */
    public static function record(User $actor, Project $project, array $changes, ?string $ip = null): void
    {
        $rows = [];
        $now = now();

        foreach ($changes as $field => [$before, $after]) {
            if (self::same($before, $after)) {
                continue;
            }

            $rows[] = [
                'actor_type' => ActorType::User->value,
                'actor_id' => $actor->id,
                'action' => 'project.'.$field.'.updated',
                'auditable_type' => Project::class,
                'auditable_id' => $project->id,
                'changes' => json_encode(['field' => $field, 'from' => $before, 'to' => $after]),
                'ip_address' => $ip,
                'created_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        // One insert for the whole save. A settings form touching six fields
        // should not be six round trips.
        AuditLog::query()->insert($rows);
    }

    /**
     * A lone event with no before/after — archiving, restoring, deleting.
     */
    public static function event(User $actor, Project $project, string $action, ?string $ip = null): void
    {
        AuditLog::query()->create([
            'actor_type' => ActorType::User,
            'actor_id' => $actor->id,
            'action' => 'project.'.$action,
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'changes' => null,
            'ip_address' => $ip,
        ]);
    }

    /**
     * Lists compare as sets: reordering countries is not a change to which
     * countries were picked, and recording one would fill the history with
     * entries nobody made.
     */
    private static function same(mixed $before, mixed $after): bool
    {
        if (is_array($before) && is_array($after)) {
            sort($before);
            sort($after);
        }

        return $before === $after;
    }
}
