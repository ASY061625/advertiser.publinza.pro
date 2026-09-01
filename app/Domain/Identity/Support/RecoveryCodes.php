<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Eight single-use codes that get someone back in when the authenticator is on
 * a phone they no longer have.
 *
 * Stored hashed, so this class can generate them and check them but cannot show
 * them again. That is the point: the plaintext exists once, in the response
 * that created them.
 */
final class RecoveryCodes
{
    public const COUNT = 8;

    /**
     * @return list<string> The plaintext codes. Show these once and discard.
     */
    public static function generate(User $user): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            // Two groups of five from an unambiguous alphabet: no O/0 or I/1, so
            // reading one off paper does not fail on a character.
            $code = Str::upper(Str::password(5, letters: true, numbers: true, symbols: false, spaces: false))
                .'-'
                .Str::upper(Str::password(5, letters: true, numbers: true, symbols: false, spaces: false));

            $code = strtr($code, ['O' => 'M', '0' => '2', 'I' => 'K', '1' => '7', 'L' => 'J']);

            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashed, JSON_THROW_ON_ERROR)),
        ])->save();

        return $plain;
    }

    /**
     * Spends a code if it matches. Each one works exactly once — a used code is
     * removed rather than marked, so a stolen list shrinks as it is used.
     */
    public static function consume(User $user, string $candidate): bool
    {
        $hashes = self::hashesFor($user);
        $candidate = Str::upper(trim($candidate));

        foreach ($hashes as $index => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($hashes[$index]);

                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(
                        json_encode(array_values($hashes), JSON_THROW_ON_ERROR),
                    ),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public static function remaining(User $user): int
    {
        return count(self::hashesFor($user));
    }

    /**
     * @return list<string>
     */
    private static function hashesFor(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        /** @var list<string> $hashes */
        $hashes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR);

        return $hashes;
    }
}
