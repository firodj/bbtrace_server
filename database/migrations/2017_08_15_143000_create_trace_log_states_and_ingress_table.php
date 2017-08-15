<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTraceLogStatesAndIngressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trace_log_states', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('pkt_no');
            $table->integer('thread');
            $table->integer('last_block_id');
            $table->json('stacks');
        });

        Schema::create('flows', function(Blueprint $table) {
            $table->integer('id');
            $table->integer('last_block_id');
            $table->primary(['id', 'last_block_id']);
            $table->integer('xref');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trace_log_states');
        Schema::dropIfExists('flows');
    }
}
