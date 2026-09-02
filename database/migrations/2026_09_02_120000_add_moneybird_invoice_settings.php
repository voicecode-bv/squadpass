<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('moneybird_ledger_account_id', 30)->nullable()->after('moneybird_administration_id');
            $table->string('moneybird_tax_rate_id', 30)->nullable()->after('moneybird_ledger_account_id');
            $table->string('moneybird_workflow_id', 30)->nullable()->after('moneybird_tax_rate_id');
            $table->string('moneybird_document_style_id', 30)->nullable()->after('moneybird_workflow_id');
            $table->string('moneybird_invoice_delivery', 20)->default('draft')->after('moneybird_document_style_id');
            $table->boolean('moneybird_sync_contacts')->default(true)->after('moneybird_invoice_delivery');
            $table->text('moneybird_last_error')->nullable()->after('moneybird_sync_contacts');
            $table->timestamp('moneybird_last_synced_at')->nullable()->after('moneybird_last_error');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('moneybird_credit_invoice_id')->nullable()->after('moneybird_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('moneybird_credit_invoice_id');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'moneybird_ledger_account_id', 'moneybird_tax_rate_id', 'moneybird_workflow_id',
                'moneybird_document_style_id', 'moneybird_invoice_delivery', 'moneybird_sync_contacts',
                'moneybird_last_error', 'moneybird_last_synced_at',
            ]);
        });
    }
};
