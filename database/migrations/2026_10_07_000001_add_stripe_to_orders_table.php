<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Card payments go through Stripe Checkout. An order remembers its latest
 * Checkout Session, so a returning customer or Stripe's webhook can be
 * matched to it, and the payment it produced, so the admin can open it in
 * Stripe and refunds can be traced back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('payment_status')->index();
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_session_id')->index();
            $table->boolean('confirmation_sent')->default(false)->after('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stripe_session_id', 'stripe_payment_intent_id', 'confirmation_sent']);
        });
    }
};
