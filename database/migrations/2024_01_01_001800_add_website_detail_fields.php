<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            /*
             * The placement terms a buyer reads before committing.
             *
             * All of them were previously implicit — answered in a message, or
             * discovered after the order. Each one is a column because each one
             * is a fact about the publisher that does not change per order, and
             * because "does this site mark posts as sponsored" is a question
             * whose answer decides whether a link is worth buying at all.
             */
            $table->boolean('marks_sponsored')->default(false)->after('link_type');

            // How long the publisher guarantees the link stays up. Zero means
            // no guarantee, which is a real answer and a different one from
            // "we have not asked" — hence not nullable.
            $table->unsignedSmallInteger('link_guarantee_months')->default(0)->after('marks_sponsored');

            $table->boolean('accepts_images')->default(true)->after('link_guarantee_months');
            $table->boolean('accepts_embeds')->default(false)->after('accepts_images');

            // When the domain was first registered, for the "domain age" tile.
            // Nullable: it comes from WHOIS, which is not always readable.
            $table->date('domain_registered_at')->nullable()->after('accepts_embeds');
        });

        Schema::table('website_metrics', function (Blueprint $table): void {
            // What the organic traffic would cost to buy, in cents.
            $table->unsignedBigInteger('traffic_value_cents')->default(0)->after('organic_keywords');

            // Pages the search engine holds for this domain.
            $table->unsignedBigInteger('indexed_pages')->default(0)->after('traffic_value_cents');
        });

        /*
         * Examples of the publisher's own work.
         *
         * A table rather than a JSON column on `websites`: these are rows an
         * admin curates one at a time, they carry their own dates, and the
         * detail view reads the newest three. A JSON array would make "add one
         * more sample" a read-modify-write of the whole set.
         */
        Schema::create('website_sample_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('title', 190);
            $table->string('url', 2048);
            $table->date('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['website_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sample_posts');

        Schema::table('website_metrics', function (Blueprint $table): void {
            $table->dropColumn(['traffic_value_cents', 'indexed_pages']);
        });

        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn([
                'marks_sponsored',
                'link_guarantee_months',
                'accepts_images',
                'accepts_embeds',
                'domain_registered_at',
            ]);
        });
    }
};
