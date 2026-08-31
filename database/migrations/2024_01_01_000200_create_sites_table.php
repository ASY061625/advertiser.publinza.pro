<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 190)->unique();
            $table->string('language', 8)->index();
            $table->string('category', 64)->index();
            $table->unsignedInteger('price_minor_units');
            $table->unsignedInteger('traffic')->default(0);
            $table->unsignedTinyInteger('domain_rating')->default(0);
            $table->unsignedTinyInteger('domain_authority')->default(0);
            $table->unsignedTinyInteger('spam_score')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // The catalog's default sort and its cheapest filters.
            $table->index(['status', 'traffic']);
            $table->index(['status', 'price_minor_units']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
