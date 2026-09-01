<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Shared lookup tables. Small, stable, and seeded rather than user-created. */
    public function up(): void
    {
        Schema::create('website_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 96)->unique();
            $table->string('slug', 96)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 5)->unique();      // ISO 639-1, e.g. "en", "pt-BR"
            $table->string('name', 64);
            $table->string('native_name', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 2)->unique();       // ISO 3166-1 alpha-2
            $table->string('name', 96);
            $table->string('region', 64)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sensitive_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 96)->unique();
            $table->string('slug', 96)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_topics');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('website_categories');
    }
};
