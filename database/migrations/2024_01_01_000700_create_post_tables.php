<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('project_folders')->nullOnDelete();
            $table->foreignId('website_id')->constrained()->restrictOnDelete();
            // Backed by App\Domain\Posts\Enums\PostStatus. The legal moves live
            // in that enum, not in a database enum.
            $table->string('status', 32)->default('draft');
            $table->string('anchor_text', 190)->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->string('content_mode', 32);
            $table->foreignId('article_id')->nullable();
            $table->unsignedBigInteger('price_cents');
            // When the 3-day verification window closes and frozen funds are
            // released to platform revenue.
            $table->timestamp('frozen_until')->nullable();
            $table->string('published_url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The advertiser's own post list, filtered by status, newest first.
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['project_id', 'status']);
            $table->index(['website_id', 'status']);
        });

        Schema::create('post_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Null on the row written when the post is created.
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The audit trail is read as a timeline per post.
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->longText('body_html')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('file_path', 512)->nullable();
            // Drafts are versioned rather than overwritten, so a rejected
            // revision stays readable next to the one that replaced it.
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('submitted_by', 16)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'version']);
        });

        // Deferred: posts.article_id points at the current revision, and
        // articles.post_id points back, so neither table can be created first
        // with both constraints in place.
        Schema::table('posts', function (Blueprint $table): void {
            $table->foreign('article_id')->references('id')->on('articles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['article_id']);
        });

        Schema::dropIfExists('articles');
        Schema::dropIfExists('post_status_history');
        Schema::dropIfExists('posts');
    }
};
