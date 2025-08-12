<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->char('client_id', 36);
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->timestamps();

            $table->index(['user_id', 'client_id']);
            $table->unique(['slug', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
