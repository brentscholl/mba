<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->date('DOS')->nullable();
            $table->string('HCPCsCode')->nullable();
            $table->string('Modifier')->nullable();
            $table->text('Description')->nullable();
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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
