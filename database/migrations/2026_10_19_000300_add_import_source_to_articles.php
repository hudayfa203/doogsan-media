<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Where an imported story came from (e.g. "wordpress") and its id there,
            // so re-running the importer never duplicates a story.
            $table->string('import_source', 30)->nullable();
            $table->string('import_id', 64)->nullable();
            $table->unique(['import_source', 'import_id']);
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['import_source', 'import_id']);
            $table->dropColumn(['import_source', 'import_id']);
        });
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['source_url']);
            $table->dropColumn('source_url');
        });
    }
};
