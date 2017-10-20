<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('graph_nodes', function(Blueprint $table) {
            $table->index('subroutine_id');
        });
        Schema::table('graph_links', function(Blueprint $table) {
            $table->index('source_id');
            $table->index('target_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('graph_nodes', function(Blueprint $table) {
            $table->dropIndex(['subroutine_id']);
        });
        Schema::table('graph_links', function(Blueprint $table) {
            $table->dropIndex(['source_id']);
            $table->dropIndex(['target_id']);
        });
    }
}
