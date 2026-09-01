<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('name', 120);
            $table->string('company', 190)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('vat_no', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 8)->default('en');
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            // Backed by App\Domain\Identity\Enums\UserStatus, not a DB enum.
            $table->string('status', 32)->default('active')->index();
            $table->string('referrer_source', 120)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // Named for what config/auth.php asks for. Advertisers and admins get
        // separate tables rather than sharing one keyed on email: the two live
        // in different tables and may share an address, and a single shared
        // table would let a token issued for the advertiser broker be redeemed
        // against the admin one.
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('guard', 16)->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('created_at')->useCurrent();

            // Powers "too many attempts from this address" lookups.
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
