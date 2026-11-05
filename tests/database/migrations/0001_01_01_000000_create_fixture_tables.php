<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Javaabu\Meta\Support\MetaTable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_meta', fn (Blueprint $table) => MetaTable::columns($table, 'product_meta'));
    }

    public function down(): void
    {
        Schema::dropIfExists('product_meta');
        Schema::dropIfExists('products');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('posts');
    }
};
