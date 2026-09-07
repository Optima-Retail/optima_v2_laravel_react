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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 10)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('team_leader_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('timezone_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('telephony_phone_number', 30)->nullable();
            $table->string('pbx_extension', 20)->nullable();
            $table->string('telegram_user_id')->nullable();
            $table->string('external_hr_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_internal_employee')->default(false);
            $table->boolean('is_team_account')->default(false);
            $table->boolean('is_preventive_specialist')->nullable();
            $table->decimal('performance_factor', 5, 2)->default(1);
            $table->decimal('invoiced_revenue_target', 10, 2)->nullable();
            $table->decimal('quality_score', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('budget_approval_limit', 10, 2)->default(0);
            $table->boolean('sso_only')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->text('totp_secret')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('team_leader_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
