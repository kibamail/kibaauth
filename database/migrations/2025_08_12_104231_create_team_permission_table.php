<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_permission', function (Blueprint $table) {
            $table->id();
            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->uuid('permission_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['team_id', 'permission_id']);
            $table->index('team_id');
            $table->index('permission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_permission');
    }
};
