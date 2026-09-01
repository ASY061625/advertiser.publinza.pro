<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Null means the advertiser has never signed in. Login reads it
            // before writing it, which is how the first sign-in is routed to
            // project creation instead of the dashboard.
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Eight single-use codes, each hashed. Shown once at generation and
            // never recoverable afterwards.
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // Set only once a code from the authenticator has been verified, so
            // a half-finished setup cannot lock anyone out.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The cookie holds the plaintext token; only its hash is stored, so
            // a database leak does not hand over working 2FA bypasses.
            $table->string('token_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
