<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read-only MCP access tokens, each bound to a mark1 user. The token is
     * shown once at creation; only its sha256 hash is stored here. Access is
     * granted purely by the existence of a non-revoked row — the admin UI
     * (McpTokenController) is the on/off switch. Nothing in the app auth stack
     * or the users table is modified.
     */
    public function up(): void
    {
        Schema::create('mcp_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // human label, e.g. "boss's laptop"
            $table->string('token_hash', 64)->unique();      // sha256 hex of the plaintext token
            $table->string('token_last_four', 8)->nullable();// non-secret tail, for display only
            $table->timestamp('last_used_at')->nullable();   // reserved (read-only MCP user cannot write it)
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_access_tokens');
    }
};
