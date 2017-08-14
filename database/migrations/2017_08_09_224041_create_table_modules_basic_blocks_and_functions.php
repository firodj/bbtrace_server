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
            $table->integer('id')->primary();
            $table->integer('end')->index();
            $table->integer('module_id')->index();
            $table->integer('subroutine_id')->nullable()->index();
            $table->integer('jump_addr');
            $table->string('jump_mnemonic');
            $table->integer('jump_operand')->nullable();
        });

        Schema::create('modules', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('entry');
            $table->integer('end');
            $table->string('name');
            $table->string('path');
        });

        Schema::create('symbols', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('module_id')->index();
            $table->string('name');
            $table->integer('ordinal');
        });

        Schema::create('subroutines', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('end');
            $table->integer('module_id')->index();
            $table->string('name');
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
    }
}
