<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Pages\ManagePlatformSettings;
use Misaf\VendraConsole\Filament\Resources\Invoices\InvoiceResource;
use Misaf\VendraConsole\Filament\Resources\Invoices\Pages\ListInvoices;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Settings\BillingSettings;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Contracts\BillingProfile;
use Misaf\VendraSubscription\Models\SubscriptionInvoice;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $admin = User::factory()->withAppAuthentication()->create(['tenant_id' => null]);
    Console::factory()->active()->for($admin)->create();
    actingAs($admin, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));
});

it('saves the seller and the tax rate that every new charge uses', function (): void {
    livewire(ManagePlatformSettings::class)
        ->assertFormSet(['tax_rate_percentage' => 0, 'tax_label' => 'VAT'])
        ->fillForm(['seller_name' => ' Vendra GmbH ', 'seller_address' => '', 'seller_tax_id' => 'DE123', 'tax_rate_percentage' => '19.5', 'tax_label' => 'MwSt'])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = resolve(BillingProfile::class);

    expect(resolve(BillingSettings::class)->tax_rate)->toBe(1_950)
        ->and($profile->taxRate())->toBe(1_950)
        ->and($profile->taxLabel())->toBe('MwSt')
        ->and($profile->seller())->toBe(['name' => 'Vendra GmbH', 'address' => null, 'tax_id' => 'DE123']);
});

it('names the brand as the seller until a seller name is set', function (): void {
    expect(Arr::get(resolve(BillingProfile::class)->seller(), 'name'))->toBe('Vendra Console');
});

it('rejects a tax rate above 100 percent', function (): void {
    livewire(ManagePlatformSettings::class)
        ->fillForm(['tax_rate_percentage' => 150])
        ->call('save')
        ->assertHasFormErrors(['tax_rate_percentage']);
});

it('lists invoices by reseller and downloads them', function (): void {
    $reseller = Reseller::factory()->active()->create();
    $invoice = SubscriptionInvoice::factory()->forSubscriber($reseller)->create();
    $other = SubscriptionInvoice::factory()->forSubscriber(Reseller::factory()->active()->create())->create();

    $this->get(InvoiceResource::getUrl())->assertOk();

    livewire(ListInvoices::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$invoice, $other])
        ->filterTable('reseller', $reseller->id)
        ->assertCanSeeTableRecords([$invoice])
        ->assertCanNotSeeTableRecords([$other])
        ->callAction(TestAction::make('download')->table($invoice))
        ->assertFileDownloaded($invoice->downloadName());
});
