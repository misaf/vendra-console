<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->repository('global');
        $this->migrator->add('console.brand_name', 'Vendra Console');
    }
};
