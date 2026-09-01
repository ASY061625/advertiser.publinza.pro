<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('website_url', 2048);
            $table->foreignId('category_id')->nullable()->constrained('website_categories')->nullOnDelete();
            $table->string('status', 32)->default('active');
            // Default instructions passed to the writer for every post here.
            $table->text('publisher_task')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // Targeting pivots: the countries, languages and sensitive topics a
        // project is interested in.
        Schema::create('project_countries', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_id', 'country_id']);
        });

        Schema::create('project_languages', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_id', 'language_id']);
        });

        Schema::create('project_sensitive_topics', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensitive_topic_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_id', 'sensitive_topic_id']);
        });

        Schema::create('project_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // Overrides the project's publisher_task when set.
            $table->text('publisher_task')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('landing_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('project_folders')->nullOnDelete();
            $table->string('anchor_text', 190);
            $table->string('url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('project_folders');
        Schema::dropIfExists('project_sensitive_topics');
        Schema::dropIfExists('project_languages');
        Schema::dropIfExists('project_countries');
        Schema::dropIfExists('projects');
    }
};
