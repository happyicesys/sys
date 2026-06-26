<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_ticket_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('refund_ticket_id')->index();
            $table->bigInteger('actor_id')->nullable();   // null = system / customer
            $table->string('actor_label')->nullable();    // 'Customer', 'System', or user name
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_ticket_logs');
    }
};
