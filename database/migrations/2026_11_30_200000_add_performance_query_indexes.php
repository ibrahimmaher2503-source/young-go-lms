<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', static function (Blueprint $table): void {
            $table->index(['learner_id', 'starts_at'], 'entitlements_learner_starts_idx');
        });

        Schema::table('notification_dispatches', static function (Blueprint $table): void {
            $table->index(['recipient_id', 'id'], 'notification_dispatches_recipient_id_idx');
        });

        Schema::table('user_devices', static function (Blueprint $table): void {
            $table->index(['user_id', 'last_seen_at'], 'user_devices_user_last_seen_idx');
        });

        Schema::table('orders', static function (Blueprint $table): void {
            $table->index('paid_at', 'orders_paid_at_idx');
        });

        // ProcessPaymentWebhook locks the attempt by signed provider order id; without this it scans and locks the table.
        Schema::table('payment_attempts', static function (Blueprint $table): void {
            $table->index(['gateway', 'provider_order_id'], 'payment_attempts_gateway_provider_order_idx');
        });

        Schema::table('courses', static function (Blueprint $table): void {
            $table->index(['status', 'category_id'], 'courses_status_category_idx');
            $table->index(['status', 'created_at'], 'courses_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('courses', static function (Blueprint $table): void {
            $table->dropIndex('courses_status_created_idx');
            $table->dropIndex('courses_status_category_idx');
        });

        Schema::table('payment_attempts', static function (Blueprint $table): void {
            $table->dropIndex('payment_attempts_gateway_provider_order_idx');
        });

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex('orders_paid_at_idx');
        });

        Schema::table('user_devices', static function (Blueprint $table): void {
            $table->dropIndex('user_devices_user_last_seen_idx');
        });

        Schema::table('notification_dispatches', static function (Blueprint $table): void {
            $table->dropIndex('notification_dispatches_recipient_id_idx');
        });

        Schema::table('entitlements', static function (Blueprint $table): void {
            $table->dropIndex('entitlements_learner_starts_idx');
        });
    }
};
