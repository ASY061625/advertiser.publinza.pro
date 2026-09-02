<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which grid the preset belongs to. Posts is the first, and the
            // catalog and orders will want the same thing.
            $table->string('surface', 32)->default('posts');
            $table->string('name', 80);
            // The filter state as the query string it serialises to, so a saved
            // view and a shared URL are the same object and restoring one is
            // just applying the query.
            $table->json('query');
            $table->timestamps();

            // One name per surface per advertiser: saving over a name should
            // replace that view, not quietly create a second one beside it.
            $table->unique(['user_id', 'surface', 'name']);
            $table->index(['user_id', 'surface']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // Column order and hidden columns, keyed by surface. Stored per
            // account rather than in localStorage alone so a grid someone has
            // arranged follows them to another browser.
            $table->json('grid_preferences')->nullable()->after('changelog_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('grid_preferences');
        });

        Schema::dropIfExists('saved_views');
    }
};
