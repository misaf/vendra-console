<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->repository('global');
        $this->migrator->add('billing.seller_name');
        $this->migrator->add('billing.seller_address');
        $this->migrator->add('billing.seller_tax_id');
        $this->migrator->add('billing.tax_rate', 0);
        $this->migrator->add('billing.tax_label', 'VAT');
    }
};
