<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

final readonly class RegistrationData
{
    public function __construct(
        public string $name,
        public string $email,
        /** Plaintext. The model's `hashed` cast turns it into Argon2id on write. */
        public string $password,
        public ?string $company = null,
        public ?string $country = null,
        public ?string $referrerSource = null,
        public string $timezone = 'UTC',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: (string) $attributes['name'],
            email: mb_strtolower(trim((string) $attributes['email'])),
            password: (string) $attributes['password'],
            company: $attributes['company'] ?? null,
            country: $attributes['country'] ?? null,
            referrerSource: $attributes['referrer_source'] ?? null,
            timezone: (string) ($attributes['timezone'] ?? 'UTC'),
        );
    }
}
