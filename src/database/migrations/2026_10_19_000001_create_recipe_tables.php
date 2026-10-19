<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('servings', 8, 2)->nullable();
            $table->string('yield_label')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedSmallInteger('cook_minutes')->nullable();
            $table->unsignedSmallInteger('total_minutes')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->string('visibility', 16)->default('unlisted');
            $table->char('share_token', 32)->unique();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedBigInteger('cover_photo_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'visibility']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 32)->default('amber');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_recipe', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'recipe_id']);
        });

        Schema::create('ingredient_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->decimal('quantity', 10, 4)->nullable();
            $table->decimal('quantity_max', 10, 4)->nullable();
            $table->string('unit', 24)->nullable();
            $table->string('name');
            $table->string('note')->nullable();
            $table->string('original_text')->nullable();
        });

        Schema::create('steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('body');
            $table->unsignedSmallInteger('timer_minutes')->nullable();
        });

        Schema::create('cook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->date('cooked_on');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('scale_used', 8, 3)->default(1);
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cook_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk_path');
            $table->string('thumb_path')->nullable();
            $table->string('medium_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['photos', 'cook_logs', 'steps', 'ingredients', 'ingredient_groups', 'category_recipe', 'categories', 'recipes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
