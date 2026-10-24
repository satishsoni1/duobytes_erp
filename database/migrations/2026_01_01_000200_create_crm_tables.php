<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('type', 30)->default('individual');
            $table->string('phone', 20)->nullable()->index();
            $table->string('alt_phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('tax_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 15)->nullable();
            $table->string('source', 30)->nullable();
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->boolean('whatsapp_opt_in')->default(true);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('whatsapp', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('source', 30)->default('other')->index();
            $table->string('source_ref')->nullable()->index();
            $table->string('campaign')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->string('priority', 10)->default('warm');
            $table->text('requirement')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamp('last_contacted_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lost_reason', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('note');
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->morphs('followable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('call');
            $table->string('title');
            $table->timestamp('due_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
    }
};
