<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');

            $table->string('PatientID');
            $table->string('InsuredInsId');
            $table->string('OrderNumber');
            $table->string('Payee')->nullable();
            $table->string('DealerInvoiceNo')->nullable();
            $table->date('DOS')->nullable(); // Date of Service
            $table->string('HCPCsCode')->nullable();
            $table->string('Modifier')->nullable();
            $table->text('Description')->nullable(); // Will add index via raw SQL
            $table->integer('BilledQuantity')->nullable();
            $table->decimal('ProviderAmountEach', 10, 2)->nullable();
            $table->decimal('ProviderAmountTotal', 10, 2)->nullable();
            $table->decimal('LineItemAPBalance', 10, 2)->nullable();
            $table->decimal('AppliedAPAmount', 10, 2)->nullable();
            $table->string('PaymentNumber')->nullable();
            $table->string('PaymentType')->nullable();
            $table->date('PaymentDate')->nullable();
            $table->string('DuplicateOrderStop')->nullable();
            $table->timestamps();

            // Standard indexes
            $table->index('file_id');
            $table->index('PatientID');
            $table->index('InsuredInsId');
            $table->index('OrderNumber');
            $table->index('Payee');
            $table->index('HCPCsCode');
            $table->index('PaymentDate');
            $table->index('PaymentNumber');
            $table->index('PaymentType');
            $table->index('DOS');
            $table->index('BilledQuantity');
            $table->index('ProviderAmountEach');
            $table->index('ProviderAmountTotal');

            // Composite indexes for performance on common queries
            $table->index(['file_id', 'HCPCsCode']);
            $table->index(['file_id', 'PatientID']);
            $table->index(['file_id', 'PaymentDate']);
            $table->index(['file_id', 'PatientID', 'HCPCsCode']);
            $table->index(['file_id', 'DOS', 'HCPCsCode']);
        });

        // Prefix index on TEXT column (only works for MySQL)
        DB::statement('CREATE INDEX invoices_description_index ON invoices (Description(255))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
