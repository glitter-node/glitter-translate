<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('glitter_translate_translations', 'content_mode')) {
            Schema::table('glitter_translate_translations', function (Blueprint $table): void {
                $table->string('content_mode', 20)->nullable()->after('value');
            });
        }

        DB::table('glitter_translate_translations')
            ->where('field', 'content')
            ->whereNull('content_mode')
            ->update(['content_mode' => 'text']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('glitter_translate_translations', 'content_mode')) {
            Schema::table('glitter_translate_translations', function (Blueprint $table): void {
                $table->dropColumn('content_mode');
            });
        }
    }
};
