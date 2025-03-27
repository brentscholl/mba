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
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->string('key'); // e.g., 'duplicate_charges'
            $table->string('title')->index();
            $table->unsignedInteger('count')->default(0);
            $table->json('items')->nullable(); // chunked detail data
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
