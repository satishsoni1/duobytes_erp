<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('tally_guid', 100)->nullable()->unique()->after('notes');
            $table->string('tally_voucher_number', 50)->nullable()->index()->after('tally_guid');
            $table->string('tally_voucher_type', 50)->nullable()->after('tally_voucher_number');
            $table->unsignedBigInteger('tally_alter_id')->nullable()->after('tally_voucher_type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('tally_ledger_name')->nullable()->index()->after('notes');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('tally_name')->nullable()->index()->after('hsn_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('tally_name'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('tally_ledger_name'));
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['tally_guid']);
            $table->dropIndex(['tally_voucher_number']);
            $table->dropColumn(['tally_guid', 'tally_voucher_number', 'tally_voucher_type', 'tally_alter_id']);
        });
    }
};
