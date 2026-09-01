<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's own notifications table shape, so the framework's
        // Notifiable trait works without a custom channel.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('changelog_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->text('body');
            $table->string('category', 32)->default('improvement');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 96)->index();
            $table->string('auditable_type', 96)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string');
            $table->string('group', 64)->default('general')->index();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->json('filters')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->string('file_path', 512)->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('changelog_entries');
        Schema::dropIfExists('notifications');
    }
};
