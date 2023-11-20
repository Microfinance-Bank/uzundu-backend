<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string("account_name", 100);
            $table->string("account_number", 100);
            $table->enum('account_type',['SavingsOrCurrent']);
            $table->tinyInteger('active')->default(0);
            $table->enum('currency_code', ['161'])->default(161);
            $table->string('email', 100)->nullable();
            $table->string("added_by", 100);
            $table->string("phone", 12);
            $table->unsignedBigInteger("user_id");
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
