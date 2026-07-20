<?php

namespace App\Http\Controllers;

use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class McpTokenController extends Controller
{
    /**
     * List issued tokens (active first) and the active users an admin can issue
     * to. Route is gated by `can:read mcp-tokens`; mutations by
     * `can:manage mcp-tokens`.
     */
    public function index(Request $request)
    {
        $tokens = McpAccessToken::with('user:id,name,email')
            ->orderByRaw('revoked_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($t) {
                return [
                    'id'         => $t->id,
                    'user_id'    => $t->user_id,
                    'user_name'  => $t->user?->name,
                    'user_email' => $t->user?->email,
                    'name'       => $t->name,
                    'last_four'  => $t->token_last_four,
                    'revoked'    => $t->revoked_at !== null,
                    'created_at' => optional($t->created_at)->toDateTimeString(),
                    'revoked_at' => optional($t->revoked_at)->toDateTimeString(),
                ];
            });

        $users = User::where('is_active', true)
            ->with('operator:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'operator_id'])
            ->map(function ($u) {
                $label = $u->name;
                if ($u->email) {
                    $label .= ' — ' . $u->email;
                }
                if ($u->operator && $u->operator->code) {
                    $label .= '  (' . $u->operator->code . ')';
                }
                return ['id' => $u->id, 'label' => $label];
            });

        return Inertia::render('McpToken/Index', [
            'tokens'      => $tokens,
            'users'       => $users,
            'permissions' => [
                'manage' => $request->user()->can('manage mcp-tokens'),
            ],
        ]);
    }

    /**
     * Mint a new token for a user. The plaintext is returned exactly once via a
     * one-shot flash value (mcpNewToken); only its hash is persisted.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'name'    => 'required|string|max:191',
        ]);

        $plain = 'mk1_' . Str::random(48);

        McpAccessToken::create([
            'user_id'         => (int) $request->user_id,
            'name'            => $request->name,
            'token_hash'      => hash('sha256', $plain),
            'token_last_four' => substr($plain, -4),
        ]);

        return redirect()->route('mcp-tokens')->with('mcpNewToken', $plain);
    }

    /**
     * Revoke a token immediately (idempotent). Kept as a soft flag so the row
     * stays for audit; the MCP server only accepts rows where revoked_at IS NULL.
     */
    public function revoke(Request $request, $id)
    {
        $token = McpAccessToken::findOrFail($id);

        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }

        return redirect()->route('mcp-tokens')->with('success', 'Token revoked.');
    }
}
