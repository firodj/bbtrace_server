<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableExpressions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expressions', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('operand_id')->index();
            $table->tinyInteger('pos')->default(0);
            $table->string('type');
            $table->integer('size');
            $table->integer('parent_id')->nullable()->index();
            $table->bigInteger('const')->nullable();
            $table->integer('domain')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expressions');
    }
}
