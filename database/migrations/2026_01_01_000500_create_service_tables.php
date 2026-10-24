<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('priority', 10)->default('medium');
            $table->text('address');
            $table->string('contact_phone', 20)->nullable();
            $table->date('scheduled_date')->nullable()->index();
            $table->string('time_slot', 10)->nullable();
            $table->text('instructions')->nullable();
            $table->text('completion_notes')->nullable();
            $table->unsignedTinyInteger('customer_rating')->nullable();
            $table->string('customer_feedback')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('repair');
            $table->string('source', 20)->default('phone');
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('open')->index();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->boolean('is_under_warranty')->default(false);
            $table->date('warranty_expires_on')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->date('visit_date')->nullable();
            $table->string('visit_slot', 10)->nullable();
            $table->text('resolution')->nullable();
            $table->decimal('service_charge', 14, 2)->default(0);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('feedback')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('installations');
    }
};
