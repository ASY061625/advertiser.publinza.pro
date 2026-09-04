<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table): void {
            /**
             * The project's own promoted domain, tracked as a row here.
             *
             * Every comparison on the tab is against this site, so it needs
             * exactly the metrics a competitor has, fetched from exactly the
             * same provider on exactly the same schedule. A second table would
             * be the same six columns and a second code path that can drift
             * from the one beside it — and the first time the two disagreed,
             * every delta on the page would be wrong in a way nobody could see.
             *
             * It is excluded from the ten-slot limit and from the table; the
             * unique key on (project_id, domain) then also means an advertiser
             * cannot add their own site as a rival to itself.
             */
            $table->boolean('is_self')->default(false)->after('project_id');

            // When a person last asked for a refresh, which is what the
            // 24-hour cooldown is measured from. Distinct from the metric's
            // own fetched_at: a scheduled refill should not spend the manual
            // allowance, and a failed attempt should not either.
            $table->timestamp('refreshed_at')->nullable()->after('added_at');

            // A row exists before its numbers do: adding one queues the fetch
            // and shows the row immediately, so the tab needs to say which of
            // the three it is looking at.
            $table->string('fetch_state', 12)->default('pending')->after('refreshed_at');
            $table->string('fetch_error', 190)->nullable()->after('fetch_state');

            $table->index(['project_id', 'is_self']);
        });

        Schema::table('competitor_metrics', function (Blueprint $table): void {
            /**
             * DR and DA become nullable, because no provider supplies both.
             *
             * Ahrefs has domain rating and no domain authority; Moz is the
             * other way round. Defaulting the absent one to 0 would print a
             * zero in a column headed with a vendor's trademark — a real
             * score, and the worst one there is — for a site nobody measured.
             * Null is the only value that means "not measured", and the tab
             * renders it as an em dash.
             */
            $table->unsignedTinyInteger('dr')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('da')->nullable()->default(null)->change();

            // What the traffic would cost to buy, in cents — money is integer
            // minor units everywhere in this codebase and this is no exception.
            $table->unsignedBigInteger('traffic_value_cents')->default(0)->after('backlinks');

            // Which provider produced this row. On the row rather than in
            // config, because config says who answers *now* and a cached row
            // has to keep saying who answered *then* — the tab prints it.
            $table->string('provider', 32)->after('traffic_value_cents');

            // Twelve monthly points, oldest first: [{month: '2026-04', traffic: 91000}, …].
            $table->json('traffic_history')->nullable()->after('provider');

            /**
             * How many keywords this domain and the project's own site both
             * rank for — one number, not the three-way split the chart draws.
             *
             * The split is shared / only-them / only-you, and two thirds of it
             * is arithmetic against the *other* row: only-them is their keyword
             * count minus the shared ones, only-you is yours minus the same.
             * Storing the split would mean writing a number about your site
             * into a competitor's row, at a moment when your site's own figures
             * may not have been fetched yet — so the row stores the fact the
             * provider actually measured, and the comparison does the sums when
             * both rows are in hand.
             */
            $table->unsignedBigInteger('shared_keywords')->nullable()->after('traffic_history');

            // The top gap keywords — ones they rank for and the project does
            // not. Capped by config before it is written, so the column holds a
            // page of results rather than an unbounded crawl.
            $table->json('gap_keywords')->nullable()->after('shared_keywords');

            // {'Technology': 34, 'Finance': 12} — referring domains by catalog
            // category, which is what the recommendation strip is derived from.
            $table->json('link_categories')->nullable()->after('gap_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_metrics', function (Blueprint $table): void {
            $table->unsignedTinyInteger('dr')->default(0)->change();
            $table->unsignedTinyInteger('da')->default(0)->change();

            $table->dropColumn([
                'traffic_value_cents', 'provider', 'traffic_history',
                'shared_keywords', 'gap_keywords', 'link_categories',
            ]);
        });

        Schema::table('competitors', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'is_self']);
            $table->dropColumn(['is_self', 'refreshed_at', 'fetch_state', 'fetch_error']);
        });
    }
};
