<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('handbook_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handbook_document_id')->constrained()->cascadeOnDelete();
            $table->string('key')->unique();
            $table->unsignedInteger('position');
            $table->text('body');
            $table->string('body_hash', 64)->index();
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
            $table->timestamps();

            $table->fullText('body');
            $table->unique(['handbook_document_id', 'position']);
        });
    }
};
