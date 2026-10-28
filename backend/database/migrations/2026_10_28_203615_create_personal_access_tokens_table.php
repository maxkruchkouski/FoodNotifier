<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('email', 100)->unique();
                $table->string('password');
                $table->string('role', 20)->default('owner'); // 'superadmin', 'owner'
                $table->timestamps();
            });
        }

        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_id', 50)->index();
            $table->string('order_number', 50);
            $table->string('status', 20)->default('pending'); // pending, cooking, ready
            $table->timestamp('cooking_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_id', 50)->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('restaurants');
        Schema::dropIfExists('users');
    }
};
