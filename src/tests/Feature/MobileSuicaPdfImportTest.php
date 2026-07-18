<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Imports\PdfTextExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MobileSuicaPdfImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->mock(
            PdfTextExtractorService::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('extract')
                ->andReturn($this->sampleText()),
        );
    }

    public function test_user_can_preview_and_commit_a_mobile_suica_pdf(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($account))
            ->assertRedirect();

        $import = Import::query()->firstOrFail();

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'source_name' => 'mobile_suica',
            'status' => 'validated',
            'total_rows' => 3,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'transaction_date' => '2026-12-31 00:00:00',
            'amount' => '300.00',
            'category_name' => '交通費',
            'subcategory_name' => '電車',
            'merchant_name' => 'モバイルSuica 東京 → 品川',
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'amount' => '200.00',
            'category_name' => null,
            'subcategory_name' => null,
            'merchant_name' => 'モバイルSuica 物販',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertSame(3, Transaction::query()->where('import_id', $import->id)->count());
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => '200.00',
            'category_id' => null,
            'subcategory_id' => null,
            'merchant_name' => 'モバイルSuica 物販',
        ]);
    }

    public function test_overlapping_pdf_rows_are_skipped_after_the_first_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
        ]);

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload($account));
        $firstImport = Import::query()->firstOrFail();
        $this->actingAs($user)->post(route('imports.commit', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload($account));
        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->assertSame(3, $secondImport->duplicate_rows);

        $this->actingAs($user)->post(route('imports.commit', $secondImport));

        $this->assertDatabaseHas('imports', [
            'id' => $secondImport->id,
            'status' => 'imported',
            'imported_rows' => 0,
            'skipped_rows' => 3,
            'duplicate_rows' => 3,
        ]);
        $this->assertSame(3, Transaction::query()->count());
    }

    public function test_mobile_suica_import_requires_the_users_e_money_account(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create(['type' => 'bank']);
        $otherUsersAccount = Account::factory()->for(User::factory()->create())->create([
            'type' => 'e_money',
        ]);

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($bankAccount))
            ->assertSessionHasErrors('account_id');

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($otherUsersAccount))
            ->assertSessionHasErrors('account_id');
    }

    /**
     * @return array{source_name: string, account_id: int, csv_file: UploadedFile}
     */
    private function uploadPayload(Account $account): array
    {
        return [
            'source_name' => 'mobile_suica',
            'account_id' => $account->id,
            'csv_file' => UploadedFile::fake()->create('mobile-suica.pdf', 100, 'application/pdf'),
        ];
    }

    private function sampleText(): string
    {
        return <<<'TEXT'
モバイルSuica 利用履歴
JE*** **** **** 6040
12 31 繰 \5,000
12 31 入 東京 出 品川 \4,700 -300
01 01 現金 \14,700 +10,000
01 02 物販 \14,500 -200
01 02 バス等 都バス \14,270 -230
01 03 紛再 \14,270 0
2027/01/10
TEXT;
    }
}
