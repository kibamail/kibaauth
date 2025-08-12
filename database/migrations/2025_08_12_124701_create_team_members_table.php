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
        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'pending'])->default('pending');
            $table->timestamps();

            // Create indexes for partial uniqueness constraints
            $table->index(['team_id', 'user_id'], 'team_members_team_user_idx');
            $table->index(['team_id', 'email'], 'team_members_team_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
