<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableModulesBasicBlocksAndFunctions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocks', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('addr')->index();
            $table->integer('end')->index();
            $table->integer('module_id')->index();
            $table->integer('subroutine_id')->nullable()->index();
            $table->integer('jump_addr')->nullable();
            $table->string('jump_mnemonic')->nullable();
            $table->integer('jump_dest')->nullable();
        });

        Schema::create('modules', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('addr')->index();
            $table->integer('entry');
            $table->integer('end');
            $table->string('name');
            $table->string('path');
        });

        Schema::create('symbols', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('addr')->index();
            $table->integer('module_id')->index();
            $table->string('name');
            $table->integer('ordinal');
        });

        Schema::create('subroutines', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('addr')->index();
            $table->integer('end');
            $table->integer('module_id')->index();
            $table->string('name');
            $table->integer('arg')->nullable();
            $table->integer('esp')->nullable();
        });

        Schema::create('references', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('addr')->index();
            $table->integer('ref_addr');
            $table->string('kind', 1);
        });

        Schema::create('flows', function(Blueprint $table) {
            $table->increments('id');
            $table->integer('block_id')->index();
            $table->string('block_type')->index();
            $table->integer('last_block_id')->index();
            $table->string('last_block_type')->index();
            $table->tinyInteger('xref');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('symbols');
        Schema::dropIfExists('subroutines');
        Schema::dropIfExists('references');
        Schema::dropIfExists('flows');
    }
}
