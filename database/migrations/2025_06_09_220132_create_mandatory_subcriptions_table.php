<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandatory_subcriptions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // ['public', 'private', 'link']
            $table->string('channelId')->nullable();
            $table->string('link');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandatory_subcriptions');
    }
};
