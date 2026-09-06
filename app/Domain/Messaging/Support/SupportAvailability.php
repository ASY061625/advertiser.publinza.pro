<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Support;

use Carbon\CarbonImmutable;

/**
 * Whether anyone is at the other end right now, and what to say if not.
 *
 * The composer shows this because the alternative is silence: somebody who
 * writes at 2am and hears nothing cannot tell "nobody is awake" from "the
 * message did not send", and the second reading is the one that generates a
 * duplicate message and then a complaint.
 */
final class SupportAvailability
{
    /**
     * @return array{online: bool, note: string}
     */
    public function state(?CarbonImmutable $now = null): array
    {
        /** @var array{timezone: string, open_hour: int, close_hour: int, days: list<int>, response_hours: int} $config */
        $config = config('publinza.support');

        $local = ($now ?? CarbonImmutable::now())->setTimezone($config['timezone']);

        $open = in_array($local->dayOfWeekIso, $config['days'], true)
            && $local->hour >= $config['open_hour']
            && $local->hour < $config['close_hour'];

        if ($open) {
            return ['online' => true, 'note' => 'The team is online now.'];
        }

        $hours = $config['response_hours'];

        return [
            'online' => false,
            // Names when they are back *and* how long a reply usually takes.
            // "We are offline" alone answers neither of the two questions
            // somebody has when they hit send outside office hours.
            'note' => sprintf(
                'The team is offline. Replies usually arrive within %d %s of opening, %s.',
                $hours,
                $hours === 1 ? 'hour' : 'hours',
                $this->schedule($config),
            ),
        ];
    }

    /**
     * @param  array{timezone: string, open_hour: int, close_hour: int, days: list<int>, response_hours: int}  $config
     */
    private function schedule(array $config): string
    {
        $hour = static fn (int $h): string => CarbonImmutable::today()->setHour($h)->format('ga');

        return sprintf(
            'Mon–Fri %s–%s %s',
            $hour($config['open_hour']),
            $hour($config['close_hour']),
            $config['timezone'],
        );
    }
}
