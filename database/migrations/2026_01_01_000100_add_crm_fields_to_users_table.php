<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 30)->default('sales')->after('phone')->index();
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('receives_leads')->default(false)->after('is_active');
            $table->timestamp('last_lead_assigned_at')->nullable()->after('receives_leads');
            $table->timestamp('last_login_at')->nullable()->after('last_lead_assigned_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'is_active', 'receives_leads', 'last_lead_assigned_at', 'last_login_at']);
        });
    }
};
