<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('related_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('status', 32)->default('active');
            $table->string('classification', 50)->default('commercial');
            $table->string('owner_reference', 80)->nullable();
            $table->string('related_reference', 80)->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('external_code', 80)->nullable();
            $table->text('notes')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_token', 64)->default('');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_company_id', 'related_company_id', 'kind', 'deleted_token'], 'company_relationships_owner_related_kind_unique');
            $table->index(['owner_company_id', 'status']);
            $table->index(['related_company_id', 'kind']);
        });

        DB::statement('ALTER TABLE company_relationships ADD CONSTRAINT company_relationships_no_self CHECK (owner_company_id <> related_company_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_relationships');
    }
};
