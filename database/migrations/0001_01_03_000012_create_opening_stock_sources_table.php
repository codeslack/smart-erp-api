<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_stock_sources', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('opening_stock_id')
                ->constrained('opening_stocks')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->string('bill_no', 100);

            $table->date('bill_date');

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'tenant_id',
                'opening_stock_id',
            ]);

            $table->index([
                'tenant_id',
                'supplier_id',
            ]);

            $table->index([
                'tenant_id',
                'bill_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_stock_sources');
    }
};