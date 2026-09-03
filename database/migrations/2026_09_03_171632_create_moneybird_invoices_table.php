<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moneybird_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credited_invoice_id')->nullable()->constrained('moneybird_invoices')->nullOnDelete();
            $table->string('moneybird_id');
            $table->string('moneybird_contact_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('reference')->nullable();
            $table->string('state')->default('draft');
            $table->date('invoice_date')->nullable();
            // Signed: credit invoices are stored as a negative total.
            $table->integer('total_price_incl_tax_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_credit_invoice')->default(false);
            $table->json('lines')->nullable();
            $table->timestamp('moneybird_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'moneybird_id']);
            $table->index(['club_id', 'invoice_date', 'id']);
            $table->index(['club_id', 'membership_id', 'invoice_date']);
            $table->index(['club_id', 'total_price_incl_tax_cents']);
            $table->index(['club_id', 'moneybird_updated_at']);
        });

        // Invoices are matched back to a member by Moneybird contact id.
        Schema::table('memberships', function (Blueprint $table) {
            $table->index(['club_id', 'moneybird_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'moneybird_contact_id']);
        });

        Schema::dropIfExists('moneybird_invoices');
    }
};
