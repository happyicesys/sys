<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sites with no CMS code had the text "null (null)" saved as their code by
     * the edit forms. Query builder on purpose: nothing about the site changed,
     * so updated_at stays as it is.
     */
    public function up(): void
    {
        DB::table('customers')->where('code', 'null (null)')->update(['code' => null]);
    }

    public function down(): void
    {
        // The text was never a real value; there is nothing to restore.
    }
};
