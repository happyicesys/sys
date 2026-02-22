<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vend_fans', function (Blueprint $table) {
            $table->index(['vend_id', 'type', 'created_at'], 'idx_vf_vid_type_created');
        });

        Schema::table('vend_temps', function (Blueprint $table) {
            $table->index(['vend_id', 'created_at'], 'idx_vt_vid_created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vend_fans', function (Blueprint $table) {
            $table->dropIndex('idx_vf_vid_type_created');
        });

        Schema::table('vend_temps', function (Blueprint $table) {
            $table->dropIndex('idx_vt_vid_created');
        });
    }
};
