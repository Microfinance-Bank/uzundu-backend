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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string("currency_code", 100);
            $table->tinyInteger("intrabank");
            $table->bigInteger("minor_amount");
            $table->bigInteger("minor_fee_amount");
            $table->bigInteger("minor_vat_amount");
            $table->string("name_enquiry_reference");
            $table->string("narration", 255);
            $table->string("Response_code", 255);
            $table->string("sink_account_name", 255);
            $table->string("sink_account_number", 255);
            $table->string("sink_account_provider_code", 255);
            $table->string("source_account_provider_code", 255);
            $table->string("source_account_provider_name", 255);
            $table->string("status", 20)->default("pending");
            $table->string("transaction_id", 255);
            $table->string("transaction_status", 255);
            $table->string("transaction_type", 255);
            $table->unsignedBigInteger("user_id");
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger("account_id");
            $table->foreign('account_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
