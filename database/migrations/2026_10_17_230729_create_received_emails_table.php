<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->unique();
            $table->string('from_email', 255);
            $table->string('from_name', 255)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['is_read', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_emails');
    }
};