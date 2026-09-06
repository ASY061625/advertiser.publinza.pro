<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What /lists needs on top of the three tables it reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table): void {
            // A flag, not a rank. Ordering thirty sites against each other is
            // work nobody does twice; "these four first" is the distinction
            // people actually make, and a boolean is the whole of it.
            $table->boolean('priority')->default(false)->after('note');
        });

        Schema::table('blacklists', function (Blueprint $table): void {
            /*
             * Who put it there.
             *
             * Two actors can block a site for an advertiser: the advertiser,
             * and Publinza on their behalf after a support conversation. The
             * column exists so the second case is legible — an advertiser who
             * finds a site missing and no memory of blocking it needs the row
             * to say "Publinza" rather than to imply they did it and forgot.
             */
            $table->string('blocked_by', 16)->default('advertiser')->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('blacklists', function (Blueprint $table): void {
            $table->dropColumn('blocked_by');
        });

        Schema::table('wishlist_items', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
