<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Fixture consumer tables
|--------------------------------------------------------------------------
|
| The host-application models the suite hangs tags on. Their keys are plain
| auto-incrementing bigints, matching the package's shipped default
| (`taxon.id_type => incrementing`), which is what makes `taggables.taggable_id`
| a bigint column. A uuid-keyed consumer is covered separately, against the
| catalog, in tests/Feature/PostgresTaggableIdTypeTest.php.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('account_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('test_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_organizations');
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_models');
    }
};
