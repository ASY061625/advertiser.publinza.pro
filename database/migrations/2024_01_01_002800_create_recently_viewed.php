<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * What the palette shows before anything is typed.
         *
         * One row per person per thing, updated in place rather than appended
         * to: an advertiser who opens the same site nine times in a morning has
         * looked at one site, and a log would push everything else out of their
         * five most recent.
         */
        Schema::create('recently_viewed', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('viewable_type', 32);
            $table->unsignedBigInteger('viewable_id');
            $table->timestamp('viewed_at')->useCurrent();

            $table->unique(['user_id', 'viewable_type', 'viewable_id'], 'recently_viewed_unique');
            // The palette's only read: this person, this kind, newest first.
            $table->index(['user_id', 'viewable_type', 'viewed_at'], 'recently_viewed_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
    }
};
