<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Console user grants link the canonical `users` identity to the
        | console panel. A console user needs no tenant or reseller
        | relationship: presence of a row authorizes panel access, its
        | absence revokes it while leaving the identity itself intact.
        */
        Schema::create('console_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique('user_id', 'console_users_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('console_users');
    }
};
