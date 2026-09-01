<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 190)->unique();
            $table->string('slug', 190)->unique();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('website_categories')->restrictOnDelete();
            $table->foreignId('primary_language_id')->constrained('languages')->restrictOnDelete();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            // Slugs of the sensitive topics this publisher will accept.
            $table->json('accepts_sensitive_topics')->nullable();
            $table->unsignedSmallInteger('publication_period_hours')->default(72);
            $table->string('link_type', 16)->default('dofollow');
            $table->unsignedTinyInteger('links_allowed')->default(1);
            $table->unsignedTinyInteger('max_links')->default(2);
            $table->unsignedSmallInteger('min_words')->default(500);
            $table->string('sample_url', 2048)->nullable();
            $table->text('guidelines')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The catalog's hot path: active sites within a category.
            $table->index(['is_active', 'category_id']);
            $table->index(['is_active', 'is_featured']);
        });

        // MySQL and PostgreSQL support full-text indexes; SQLite (used by the
        // test suite) does not, so this is applied only where it exists.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            Schema::table('websites', function (Blueprint $table): void {
                $table->fullText(['domain', 'title', 'description'], 'websites_fulltext');
            });
        }

        Schema::create('website_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 32);
            // Money is an unsigned bigint of cents. No floats, ever.
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('writing_fee_cents')->default(0);
            $table->unsignedBigInteger('express_fee_cents')->default(0);
            $table->timestamps();

            // One price row per service per site.
            $table->unique(['website_id', 'service_type']);
        });

        Schema::create('website_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_traffic')->default(0);
            $table->unsignedTinyInteger('ahrefs_dr')->default(0);
            $table->unsignedTinyInteger('moz_da')->default(0);
            $table->unsignedTinyInteger('semrush_as')->default(0);
            $table->unsignedTinyInteger('spam_score')->default(0);
            $table->unsignedBigInteger('referring_domains')->default(0);
            $table->unsignedBigInteger('organic_keywords')->default(0);
            $table->json('traffic_by_country')->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();

            // Metrics are a time series; the newest row per site is the hot read.
            $table->index(['website_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_metrics');
        Schema::dropIfExists('website_prices');
        Schema::dropIfExists('websites');
    }
};
