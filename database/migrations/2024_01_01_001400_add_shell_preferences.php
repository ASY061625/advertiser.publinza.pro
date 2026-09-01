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
            // Mirrored in localStorage so the sidebar paints in its remembered
            // state before the first server response, and stored here so it
            // follows the advertiser to a new browser.
            $table->boolean('sidebar_collapsed')->default(false)->after('locale');

            // Everything published after this is unread. A timestamp rather
            // than a pivot: entries are read in date order and nobody needs to
            // mark an individual one.
            $table->timestamp('changelog_read_at')->nullable()->after('sidebar_collapsed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['sidebar_collapsed', 'changelog_read_at']);
        });
    }
};
