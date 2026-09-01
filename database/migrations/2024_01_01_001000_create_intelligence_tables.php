<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 190);
            $table->string('label', 120)->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // The same competitor is tracked once per project.
            $table->unique(['project_id', 'domain']);
        });

        Schema::create('competitor_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organic_traffic')->default(0);
            $table->unsignedBigInteger('organic_keywords')->default(0);
            $table->unsignedTinyInteger('dr')->default(0);
            $table->unsignedTinyInteger('da')->default(0);
            $table->unsignedBigInteger('referring_domains')->default(0);
            $table->unsignedBigInteger('backlinks')->default(0);
            $table->json('top_keywords')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['competitor_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_metrics');
        Schema::dropIfExists('competitors');
    }
};
