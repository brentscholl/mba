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
        Schema::create('audit_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_report_id')->constrained()->onDelete('cascade');
            $table->json('data'); // structured info per item (e.g. reasoning, code, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_report_items');
    }
};
