<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff accounts are a separate table from advertisers on purpose: the
     * `admin` guard resolves here and nowhere else, so an advertiser record can
     * never escalate into /asylogin.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            // Dotted ability names, e.g. "websites.review", "payouts.release".
            $table->string('name', 96)->unique();
            $table->string('label', 120);
            $table->string('group', 64)->index();
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('name', 120);
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
