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
        Schema::create('customer_opening_bills', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Bill Information
            |--------------------------------------------------------------------------
            */

            $table->string('bill_no', 100);

            $table->date('bill_date');

            $table->date('due_date')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Balance
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'amount',
                18,
                4
            );

            $table->decimal(
                'balance_amount',
                18,
                4
            );

            $table->string(
                'balance_type',
                20
            );

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Unique Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'tenant_id',
                'customer_id',
                'bill_no',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'tenant_id',
                'customer_id',
            ]);

            $table->index([
                'tenant_id',
                'bill_date',
            ]);

            $table->index([
                'tenant_id',
                'due_date',
            ]);

            $table->index([
                'tenant_id',
                'balance_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_opening_bills');
    }
};
