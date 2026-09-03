<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // string, not integer: the export must keep "007" as "007"
            $table->string('registration')->nullable();
            $table->integer('score')->nullable()->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('banned')->default(false);
            $table->dateTime('joined_at')->nullable();
            $table->string('secret_note')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('customer')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('brokens', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brokens');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('players');
    }
};
