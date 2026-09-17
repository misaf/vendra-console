<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consoles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->boolean('active')
                ->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consoles');
    }
};
