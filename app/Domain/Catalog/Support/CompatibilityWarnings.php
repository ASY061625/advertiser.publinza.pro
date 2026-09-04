<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Models\Project;

/**
 * Where a site and a project disagree.
 *
 * Informational, never exclusionary. A publisher who does not take the
 * project's topic is still a site somebody might buy — for a different article,
 * or because the topic is not in this one — and hiding the row would be the
 * catalog deciding something the buyer is better placed to decide. It says what
 * is wrong and leaves it alone.
 *
 * Shared by the catalog row and the cart line so the two cannot word the same
 * problem differently, which is how a buyer ends up believing they are two
 * problems.
 */
final class CompatibilityWarnings
{
    /**
     * The project's targeting, read once. A page of a hundred rows must not ask
     * the same two questions a hundred times.
     *
     * @return array{languages: array<int, string>, topics: array<string, string>}
     */
    public static function targeting(?Project $project): array
    {
        return [
            'languages' => $project?->languages->pluck('name', 'id')->all() ?? [],
            'topics' => $project?->sensitiveTopics->pluck('name', 'slug')->all() ?? [],
        ];
    }

    /**
     * @param  array{languages: array<int, string>, topics: array<string, string>}  $targeting
     * @return list<array{kind: string, message: string}>
     */
    public static function for(Website $website, array $targeting): array
    {
        $warnings = [];
        $languages = $targeting['languages'];

        if ($languages !== [] && ! in_array($website->primary_language_id, array_keys($languages), true)) {
            $warnings[] = [
                'kind' => 'language',
                'message' => sprintf(
                    'Publishes in %s. This project targets %s.',
                    $website->primaryLanguage?->name ?? 'another language',
                    self::list(array_values($languages)),
                ),
            ];
        }

        $missing = [];

        foreach ($targeting['topics'] as $slug => $name) {
            if (! $website->acceptsTopic($slug)) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $warnings[] = [
                'kind' => 'topic',
                'message' => 'Does not accept '.self::list($missing).', which this project targets.',
            ];
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $items
     */
    public static function list(array $items): string
    {
        if (count($items) <= 2) {
            return implode(' and ', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
