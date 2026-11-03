<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_payment_intents', function (Blueprint $table) {
            $table->string('capture_txn_id', 64)->nullable()->after('provider_txn_id');
            $table->unsignedInteger('refunded_cents')->default(0)->after('captured_cents');
        });

        Schema::create('card_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_payment_intent_id')->constrained('card_payment_intents');
            $table->unsignedBigInteger('vend_transaction_id')->nullable()->index();
            $table->string('kind', 8);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->unsignedInteger('unit_cents')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('state', 12);
            $table->string('provider_refund_id', 64)->nullable();
            $table->string('error', 500)->nullable();
            $table->json('response')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        $this->backfill();
    }

    /**
     * Charges made before capture_txn_id existed: the id is in the capture's raw answer on the
     * timeline. Only a successful answer for exactly the captured amount is believed, and an answer
     * cut short by the log's body limit is still read, as the id comes first in the transaction.
     */
    public function backfill(): void
    {
        $intents = DB::table('card_payment_intents')->where('state', 'captured')->whereNull('capture_txn_id')
            ->get(['id', 'custom_order_id', 'captured_cents']);

        foreach ($intents as $intent) {
            $events = DB::table('card_payment_events')->where('custom_order_id', $intent->custom_order_id)
                ->where('event', 'provider.http')->orderByDesc('id')->get(['detail']);

            foreach ($events as $event) {
                $detail = json_decode((string) $event->detail, true);
                if (! is_array($detail) || ($detail['path'] ?? null) !== 'transactions/card/capture' || (int) ($detail['http_status'] ?? 0) !== 200) {
                    continue;
                }
                $answer = (string) ($detail['response'] ?? '');
                if (preg_match('/"transaction"\s*:\s*\{\s*"transactionId"\s*:\s*"([^"]+)"/', $answer, $id)
                    && preg_match('/"amountInCents"\s*:\s*(\d+)/', $answer, $amount)
                    && (int) $amount[1] === (int) $intent->captured_cents) {
                    DB::table('card_payment_intents')->where('id', $intent->id)->update(['capture_txn_id' => $id[1]]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_refunds');
        Schema::table('card_payment_intents', function (Blueprint $table) {
            $table->dropColumn(['capture_txn_id', 'refunded_cents']);
        });
    }
};
