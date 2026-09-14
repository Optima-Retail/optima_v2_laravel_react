<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed document chat for technician_request (legacy polymorphic chats / lineas_chats / pendientes / silenciados / archivos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_request_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technician_request_id')->unique()->constrained('technician_requests')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('technician_request_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('technician_request_chats')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('type', 32);
            $table->boolean('is_private')->default(false);
            $table->json('arguments')->nullable();
            $table->json('translatable_arguments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chat_id', 'created_at']);
        });

        Schema::create('technician_request_chat_unreads', function (Blueprint $table): void {
            $table->foreignId('chat_id')->constrained('technician_request_chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['chat_id', 'user_id']);
        });

        Schema::create('technician_request_chat_mutes', function (Blueprint $table): void {
            $table->foreignId('chat_id')->constrained('technician_request_chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['chat_id', 'user_id']);
        });

        Schema::create('technician_request_chat_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('technician_request_chat_messages')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_request_chat_message_attachments');
        Schema::dropIfExists('technician_request_chat_mutes');
        Schema::dropIfExists('technician_request_chat_unreads');
        Schema::dropIfExists('technician_request_chat_messages');
        Schema::dropIfExists('technician_request_chats');
    }
};
