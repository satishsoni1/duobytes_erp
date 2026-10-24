<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('phone', 20)->index();
            $table->string('contact_name')->nullable();
            $table->string('direction', 3); // in | out
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('template_name')->nullable();
            $table->string('media_id')->nullable();
            $table->string('media_path')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->nullableMorphs('contact');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['phone', 'created_at']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('language', 10)->default('en');
            $table->string('category', 30)->nullable();
            $table->string('status', 20)->default('APPROVED');
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('variable_count')->default(0);
            $table->json('components')->nullable();
            $table->timestamps();
            $table->unique(['name', 'language']);
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->index();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
    }
};
