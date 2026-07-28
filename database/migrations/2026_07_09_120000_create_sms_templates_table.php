<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('category', 32);
            $table->string('body', 500);
            $table->unsignedSmallInteger('max_length')->default(159);
            $table->boolean('is_active')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
