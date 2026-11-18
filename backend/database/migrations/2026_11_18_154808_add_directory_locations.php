<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('province', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('area_type', 20)->default('locality');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->constrained();
            $table->foreignId('area_id')->nullable()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('areas');
        Schema::dropIfExists('cities');
    }
};
