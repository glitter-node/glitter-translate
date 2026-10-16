<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_translate_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 100)->comment('지원 리소스 유형');
            $table->unsignedBigInteger('resource_id')->comment('원본 리소스 ID');
            $table->string('locale', 20)->comment('G7 로케일');
            $table->string('field', 20)->comment('번역 필드: title 또는 content');
            $table->longText('value')->comment('수동 번역 값');
            $table->char('source_hash', 64)->comment('저장 시점 원문 SHA-256');
            $table->timestamps();

            $table->unique(
                ['resource_type', 'resource_id', 'locale', 'field'],
                'uq_glitter_translate_resource_locale_field'
            );
            $table->index(
                ['resource_type', 'resource_id'],
                'idx_glitter_translate_resource'
            );
            $table->index('locale', 'idx_glitter_translate_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_translate_translations');
    }
};
