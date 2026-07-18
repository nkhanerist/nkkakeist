<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_user_can_view_only_their_own_imports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownImport = Import::create([
            'user_id' => $user->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'own.csv',
            'storage_path' => 'imports/test/own.csv',
            'status' => 'validated',
            'total_rows' => 2,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        Import::create([
            'user_id' => $otherUser->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'other.csv',
            'storage_path' => 'imports/test/other.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Index')
                ->has('imports.data', 1)
                ->where('imports.data.0.id', $ownImport->id)
                ->where('imports.data.0.original_filename', 'own.csv'));
    }

    public function test_user_can_upload_csv_and_generate_import_rows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
                ['1', '2026/04/11', '給与', '120000', 'りそな銀行', '収入', '給与', '4月分', '0', 'row-2'],
            ]),
        ]);

        $import = Import::first();

        $response->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'user_id' => $user->id,
            'status' => 'validated',
            'total_rows' => 2,
        ]);

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'row_number' => 2,
            'account_name' => 'd払い',
            'category_name' => null,
            'subcategory_name' => null,
            'merchant_name' => 'かっぽうぎ 天王洲店',
            'description' => 'd払い / 未分類 / 未分類',
            'detected_type' => 'expense',
            'is_calculation_target' => 1,
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'row_number' => 3,
            'merchant_name' => '給与',
            'detected_type' => 'income',
            'status' => 'ready',
        ]);
    }

    public function test_commit_can_auto_create_account_category_and_subcategory_from_money_forward_columns(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['0', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', '三井住友カード', '食費', '外食', 'ランチ', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $account = Account::where('user_id', $user->id)->where('name', '三井住友カード')->first();
        $category = Category::where('user_id', $user->id)->where('name', '食費')->first();
        $subcategory = Subcategory::where('user_id', $user->id)->where('name', '外食')->first();

        $this->assertNotNull($account);
        $this->assertNotNull($category);
        $this->assertNotNull($subcategory);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account?->id,
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'is_calculation_target' => 0,
        ]);
    }

    public function test_money_forward_uncategorized_columns_are_imported_as_null_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.category_name', null)
                ->where('rows.0.subcategory_name', null)
                ->where('rows.0.resolved_category', null)
                ->where('rows.0.resolved_subcategory', null)
                ->where('rows.0.status', 'ready')
                ->where('rows.0.validation_errors', []));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseMissing('categories', [
            'user_id' => $user->id,
            'name' => '未分類',
        ]);
        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'merchant_name' => 'かっぽうぎ 天王洲店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
    }

    public function test_money_forward_uncategorized_columns_do_not_resolve_existing_uncategorized_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'currency' => 'JPY',
        ]);
        Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'income',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.category_name', null)
                ->where('rows.0.resolved_category', null)
                ->where('rows.0.status', 'ready')
                ->where('rows.0.validation_errors', []));
    }

    public function test_user_cannot_access_another_users_import_detail_or_commit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $import = Import::create([
            'user_id' => $otherUser->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'other.csv',
            'storage_path' => 'imports/test/other.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertForbidden();
    }

    public function test_invalid_csv_is_marked_as_failed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'invalid.csv',
                mb_convert_encoding("foo,bar\n1,2\n", 'SJIS-win', 'UTF-8'),
            ),
        ]);

        $import = Import::first();

        $response->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'failed',
        ]);
    }

    public function test_failed_import_cannot_be_committed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'invalid.csv',
                mb_convert_encoding("foo,bar\n1,2\n", 'SJIS-win', 'UTF-8'),
            ),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->from(route('imports.show', $import))
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas(
                'error',
                '取込を確定できるのはプレビュー完了済みの import のみです。',
            );

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'failed',
            'imported_rows' => 0,
        ]);
    }

    public function test_preview_shows_row_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', 'invalid-date', '店舗', 'abc', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Show')
                ->has('rows', 1)
                ->where('rows.0.status', 'error')
                ->where('rows.0.validation_errors.0', '取引日を解釈できません。'));
    }

    public function test_invalid_calendar_date_is_not_normalized_and_is_shown_as_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/02/31', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.transaction_date', null)
                ->where('rows.0.status', 'error')
                ->where('rows.0.validation_errors.0', '取引日を解釈できません。'));
    }

    public function test_duplicate_candidate_is_detected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()
            ->forAccount($account)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-10',
                'type' => 'expense',
                'amount' => '990.00',
                'currency' => 'JPY',
                'merchant_name' => 'かっぽうぎ 天王洲店',
                'description' => 'd払い / 未分類 / 未分類',
                'external_id' => 'row-1',
            ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ]);

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_duplicate_candidate', true));
    }

    public function test_money_forward_rows_with_different_ids_are_not_marked_duplicate_only_by_same_amount_and_merchant(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-2'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.1.is_duplicate_candidate', false));
    }

    public function test_duplicate_rows_within_same_csv_are_marked_as_duplicate_candidates(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.1.is_duplicate_candidate', true));
    }

    public function test_commit_creates_transactions_from_ready_rows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
                ['1', '2026/04/11', '給与', '120000', 'りそな銀行', '収入', '給与', '4月分', '0', 'row-2'],
            ]),
        ]);

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'imported',
            'imported_rows' => 2,
            'skipped_rows' => 0,
        ]);

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => '990.00',
            'import_id' => $import->id,
        ]);
    }

    public function test_lowercase_id_header_is_saved_to_transaction_external_id(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload(
                [
                    ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-lower-1'],
                ],
                ['計算対象', '日付', '内容', '金額（円）', '保有金融機関', '大項目', '中項目', 'メモ', '振替', 'id'],
            ),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'external_id' => 'row-lower-1',
        ]);
    }

    public function test_utf8_bom_header_keeps_calculation_target_flag(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload(
                [
                    ['0', '2026/04/10', '対象外取引', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-bom-1'],
                ],
                ["\u{FEFF}計算対象", '日付', '内容', '金額（円）', '保有金融機関', '大項目', '中項目', 'メモ', '振替', 'ID'],
                false,
            ),
        ])->assertRedirect();

        $import = Import::first();

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'is_calculation_target' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'merchant_name' => '対象外取引',
            'is_calculation_target' => 0,
        ]);
    }

    public function test_transfer_rows_with_resolved_counterparty_are_imported_as_transfer_transactions(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'imported',
            'imported_rows' => 1,
            'skipped_rows' => 0,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'merchant_name' => '三井住友カード引落',
            'type' => 'transfer',
            'account_id' => $bankAccount->id,
            'transfer_account_id' => $cardAccount->id,
            'is_calculation_target' => 0,
        ]);
    }

    public function test_transfer_preview_includes_resolution_messages(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $theoAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
            'import_aliases' => ['THEO+docomo(dカード積立)'],
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'THEO+docomo(dカード積立)', '-30000', 'dカード', '通信費', '携帯電話', '', '1', 'transfer-resolution-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $cardAccount->update(['name' => '変更後カード名']);
        $theoAccount->update(['import_aliases' => ['変更後THEO別名']]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.transfer_resolution.source_resolution_type', 'account_name')
                ->where('rows.0.transfer_resolution.source_resolution_message', '振替元口座は口座名一致で解決しました。')
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'exact_alias')
                ->where('rows.0.transfer_resolution.destination_resolution_message', '振替先口座は取込用別名の完全一致で解決しました。')
                ->where('rows.0.transfer_resolution.unresolved_reason', null));
    }

    public function test_transfer_preview_explains_source_from_csv_side_even_when_direction_is_reversed(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード', '5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-resolution-positive-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account.name', '三井住友カード')
                ->where('rows.0.resolved_transfer_account.name', '住信SBIネット銀行')
                ->where('rows.0.transfer_resolution.source_resolution_type', 'account_name')
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'exact_account_name'));
    }

    public function test_transfer_preview_does_not_reuse_non_transfer_validation_error_as_unresolved_reason(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/13/10', '三井住友カード', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-invalid-date-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.validation_errors.0', '取引日を解釈できません。')
                ->where('rows.0.transfer_resolution.source_resolution_type', 'account_name')
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'exact_account_name')
                ->where('rows.0.transfer_resolution.unresolved_reason', null));
    }

    public function test_transfer_rows_ignore_common_import_account_and_use_row_account(): void
    {
        $user = User::factory()->create();
        $commonAccount = Account::factory()->for($user)->create([
            'name' => '共通適用口座',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $commonAccount->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'row-common-account-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name));
    }

    public function test_transfer_preview_exposes_only_users_accounts_for_manual_resolution(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $otherAccount = Account::factory()->for($otherUser)->create([
            'name' => '他人の口座',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明な振替先', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-option-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accountOptions', 2)
                ->where('rows.0.manual_resolved_transfer_account_id', null)
                ->missing('accountOptions.2')
                ->where('accountOptions.0.name', fn (string $name) => in_array($name, ['住信SBIネット銀行', '三井住友カード'], true))
                ->where('accountOptions.1.name', fn (string $name) => in_array($name, ['住信SBIネット銀行', '三井住友カード'], true)));

        $this->assertDatabaseMissing('accounts', [
            'id' => $otherAccount->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_manually_update_transfer_destination_and_revalidate_row(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明な振替先', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-transfer-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'status' => 'error',
            'resolved_transfer_account_id' => null,
        ]);

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $cardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => $cardAccount->id,
            'resolved_account_id' => $bankAccount->id,
            'resolved_transfer_account_id' => $cardAccount->id,
            'status' => 'ready',
        ]);
    }

    public function test_manual_transfer_account_is_saved_even_when_only_non_transfer_errors_remain(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/13/10', '不明な振替先', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-transfer-invalid-date-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $cardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => $cardAccount->id,
            'resolved_account_id' => $bankAccount->id,
            'resolved_transfer_account_id' => $cardAccount->id,
            'status' => 'error',
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.validation_errors.0', '取引日を解釈できません。')
                ->where('rows.0.manual_resolved_transfer_account_id', $cardAccount->id)
                ->where('rows.0.resolved_transfer_account.id', $cardAccount->id));
    }

    public function test_manual_transfer_account_still_uses_signed_direction_resolution(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明なカード返金', '5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-destination-positive-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $cardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => $cardAccount->id,
            'resolved_account_id' => $cardAccount->id,
            'resolved_transfer_account_id' => $bankAccount->id,
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.manual_resolved_transfer_account_id', $cardAccount->id)
                ->where('rows.0.resolved_transfer_account.id', $bankAccount->id));
    }

    public function test_user_can_reedit_manual_transfer_account_after_row_becomes_ready(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $firstCardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $secondCardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明なカード返金', '5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-destination-reedit-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $firstCardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $secondCardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => $secondCardAccount->id,
            'resolved_account_id' => $secondCardAccount->id,
            'resolved_transfer_account_id' => $bankAccount->id,
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => null,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => null,
            'status' => 'error',
        ]);
    }

    public function test_transfer_destination_update_rejects_same_account_and_other_users_accounts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $otherUsersAccount = Account::factory()->for($otherUser)->create([
            'name' => '他人のカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明な振替先', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'invalid-manual-transfer-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.preview', $import))
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $bankAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import))
            ->assertSessionHasErrors("resolved_transfer_account_id.{$importRow->id}");

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => null,
        ]);

        $this->actingAs($user)
            ->from(route('imports.preview', $import))
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $otherUsersAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import))
            ->assertSessionHasErrors("resolved_transfer_account_id.{$importRow->id}");

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => null,
        ]);
    }

    public function test_transfer_destination_update_rejects_original_csv_source_after_direction_flip(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '不明なカード返金', '5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-destination-positive-2'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $cardAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $this->actingAs($user)
            ->from(route('imports.preview', $import))
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $bankAccount->id,
            ])
            ->assertRedirect(route('imports.preview', $import))
            ->assertSessionHasErrors("resolved_transfer_account_id.{$importRow->id}");

        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'manual_resolved_transfer_account_id' => $cardAccount->id,
            'resolved_account_id' => $cardAccount->id,
            'resolved_transfer_account_id' => $bankAccount->id,
            'status' => 'ready',
        ]);
    }

    public function test_imported_import_cannot_update_transfer_destination(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'imported-transfer-update-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $importRow = $import->fresh()->importRows()->firstOrFail();

        $this->actingAs($user)
            ->put(route('imports.rows.update-transfer-account', [$import, $importRow]), [
                'resolved_transfer_account_id' => $cardAccount->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'imported',
        ]);
        $this->assertDatabaseHas('import_rows', [
            'id' => $importRow->id,
            'status' => 'imported',
        ]);
    }

    public function test_imported_transfer_rows_keep_persisted_transfer_resolution_messages(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'imported-transfer-resolution-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $bankAccount->update(['name' => '変更後銀行名']);
        $cardAccount->update(['import_aliases' => ['変更後カード別名']]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'imported')
                ->where('rows.0.transfer_resolution.source_resolution_type', 'account_name')
                ->where('rows.0.transfer_resolution.source_resolution_message', '振替元口座は口座名一致で解決しました。')
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'partial_match')
                ->where('rows.0.transfer_resolution.destination_resolution_message', '振替先口座は摘要 / 説明の部分一致で解決しました。')
                ->where('rows.0.transfer_resolution.unresolved_reason', null));
    }

    public function test_legacy_transfer_rows_without_persisted_resolution_still_show_explanations(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'legacy-transfer-resolution-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();
        $importRow = $import->importRows()->firstOrFail();
        $importRow->update(['transfer_resolution' => null]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.transfer_resolution.source_resolution_type', 'account_name')
                ->where('rows.0.transfer_resolution.source_resolution_message', '振替元口座は口座名一致で解決しました。')
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'partial_match')
                ->where('rows.0.transfer_resolution.destination_resolution_message', '振替先口座は摘要 / 説明の部分一致で解決しました。')
                ->where('rows.0.transfer_resolution.unresolved_reason', null));
    }

    public function test_transfer_rows_without_external_id_are_detected_as_duplicates_on_reimport(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', ''],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', ''],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_with_different_external_ids_are_detected_as_duplicates_after_direction_normalization(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-bank-side-1'],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'transfer-card-side-1'],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.0.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_from_both_sides_in_same_csv_are_marked_as_duplicate_candidates(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-bank-side-a'],
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'transfer-card-side-b'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.1.resolved_account.name', $bankAccount->name)
                ->where('rows.1.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.1.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_from_both_sides_in_same_csv_do_not_over_deduplicate_multiple_same_amount_pairs(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落A', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-bank-side-a-1'],
                ['1', '2026/04/10', '三井住友カード引落B', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-bank-side-b-1'],
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'transfer-card-side-a-1'],
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'transfer-card-side-b-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.1.resolved_account.name', $bankAccount->name)
                ->where('rows.1.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.2.resolved_account.name', $bankAccount->name)
                ->where('rows.2.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.3.resolved_account.name', $bankAccount->name)
                ->where('rows.3.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.1.is_duplicate_candidate', false)
                ->where('rows.2.is_duplicate_candidate', false)
                ->where('rows.3.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_with_same_pair_and_amount_but_different_non_account_like_descriptors_are_not_over_deduplicated(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '定期積立A', '-5000', '住信SBIネット銀行', '未分類', '未分類', '三井住友カード引落', '1', 'transfer-a-1'],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '定期積立B', '-5000', '住信SBIネット銀行', '未分類', '未分類', '三井住友カード引落', '1', 'transfer-b-1'],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.0.status', 'ready'));
    }

    public function test_transfer_rows_detect_manual_existing_transfer_as_duplicate_even_when_descriptor_differs(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->for($user)->create([
            'account_id' => $bankAccount->id,
            'transfer_account_id' => $cardAccount->id,
            'type' => 'transfer',
            'transaction_date' => '2026-04-10',
            'amount' => '5000.00',
            'merchant_name' => '手動登録の振替',
            'description' => '既存データ',
            'is_calculation_target' => false,
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'manual-duplicate-transfer-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_resolve_direction_consistently_from_credit_card_side_rows(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'row-card-side-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name));
    }

    public function test_transfer_rows_between_bank_and_credit_card_use_signed_amount_for_refund_direction(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '住信SBIネット銀行', '5000', '三井住友カード', '未分類', '未分類', '', '1', 'row-card-refund-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $bankAccount->name));
    }

    public function test_transfer_rows_between_bank_and_e_money_use_signed_amount_for_wallet_payout_direction(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $walletAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '住信SBIネット銀行', '-5000', 'kyash', '未分類', '未分類', '', '1', 'row-wallet-payout-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $walletAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $bankAccount->name));
    }

    public function test_transfer_rows_between_credit_card_and_e_money_use_signed_amount_for_reverse_direction(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $walletAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'kyash', '980', 'dカード', '未分類', '未分類', '', '1', 'row-card-wallet-reverse-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $walletAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name));
    }

    public function test_credit_card_charge_is_resolved_to_the_same_direction_from_both_wallet_and_card_rows(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'import_aliases' => ['カード MasterCard(8658)'],
        ]);
        $walletAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'other',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'カード MasterCard(8658)', '3000', 'kyash', '未分類', '未分類', '', '1', 'row-kyash-side-1'],
                ['1', '2026/04/10', 'Kyash', '-3000', 'dカード', '未分類', '未分類', '', '1', 'row-card-side-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $walletAccount->name)
                ->where('rows.1.resolved_account.name', $cardAccount->name)
                ->where('rows.1.resolved_transfer_account.name', $walletAccount->name)
                ->where('rows.1.is_duplicate_candidate', true));
    }

    public function test_transfer_rows_between_banks_use_signed_amount_for_direction(): void
    {
        $user = User::factory()->create();
        $sourceBank = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $destinationBank = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'りそな銀行', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'row-bank-bank-direction-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $sourceBank->name)
                ->where('rows.0.resolved_transfer_account.name', $destinationBank->name));
    }

    public function test_transfer_rows_resolve_counterparty_even_with_full_width_alnum_text(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $codePaymentAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'ｄ払いＢ／やよい軒川口店', '-980', 'dカード', '未分類', '未分類', '', '1', 'row-full-width-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $codePaymentAccount->name));
    }

    public function test_transfer_rows_can_resolve_counterparty_by_account_import_aliases(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'import_aliases' => ['MasterCard(8658)', 'カード MasterCard(8658)'],
        ]);
        $kyashAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'カード MasterCard(8658)', '980', 'kyash', '未分類', '未分類', '', '1', 'row-alias-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $kyashAccount->name));
    }

    public function test_theo_card_charge_prefers_dpay_exact_alias_over_securities_partial_match(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $dPayAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
            'import_aliases' => ['THEO積立/SMBC日興証券'],
        ]);
        Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/16', 'THEO積立/SMBC日興証券', '-30000', 'dカード', '未分類', '未分類', '', '1', 'row-theo-card-1'],
            ]),
        ])->assertRedirect();

        $import = Import::firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $dPayAccount->name)
                ->where('rows.0.transfer_resolution.destination_resolution_type', 'exact_alias'));
    }

    public function test_transfer_rows_can_resolve_source_account_by_import_aliases(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'import_aliases' => ['カード MasterCard(8658)'],
        ]);
        $kyashAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'kyash', '-980', 'カード MasterCard(8658)', '未分類', '未分類', '', '1', 'row-source-alias-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $cardAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $kyashAccount->name));
    }

    public function test_transfer_rows_prefer_exact_alias_match_over_partial_account_name_match(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $theoAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
            'import_aliases' => ['THEO+docomo(dカード積立)'],
        ]);
        $dBaraiAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['0', '2026/03/01', 'THEO+docomo(dカード積立)', '-30000', 'd払い', '通信費', '携帯電話', '', '1', 'row-theo-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $dBaraiAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $theoAccount->name));
    }

    public function test_transfer_rows_with_unsupported_direction_pair_are_marked_as_error(): void
    {
        $user = User::factory()->create();
        $sourceCashAccount = Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
        ]);
        $destinationPointAccount = Account::factory()->for($user)->create([
            'name' => 'dポイント',
            'type' => 'point',
            'currency' => 'JPY',
            'import_aliases' => ['dポイント振替'],
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['0', '2026/03/01', 'dポイント振替', '-30000', '現金', '未分類', '未分類', '', '1', 'row-cash-point-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'error')
                ->where('rows.0.resolved_account.name', $sourceCashAccount->name)
                ->where('rows.0.resolved_transfer_account', null)
                ->where('rows.0.validation_errors.0', '振替方向を安全に特定できないため、相手口座を自動決定できません。'));
    }

    public function test_transfer_rows_with_securities_outflow_use_signed_amount_to_resolve_direction(): void
    {
        $user = User::factory()->create();
        $theoAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['0', '2026/03/01', '住信SBIネット銀行', '-30000', 'THEO', '未分類', '未分類', '', '1', 'row-theo-outflow-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $theoAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $bankAccount->name));
    }

    public function test_transfer_rows_preview_as_not_calculation_target_even_when_csv_marks_them_as_target(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'row-transfer-target-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.is_calculation_target', false)
                ->where('rows.0.resolved_category', null)
                ->where('rows.0.resolved_subcategory', null)
                ->where('rows.0.calculation_target_source', null));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'type' => 'transfer',
            'merchant_name' => '三井住友カード引落',
            'is_calculation_target' => 0,
        ]);
    }

    public function test_transfer_rows_without_resolved_counterparty_are_not_imported(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '口座振替', '-5000', 'd払い', '未分類', '未分類', '', '1', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'error')
                ->where('rows.0.validation_errors.0', '振替元口座を特定できません。'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'imported',
            'imported_rows' => 0,
            'skipped_rows' => 1,
        ]);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'merchant_name' => '口座振替',
            'type' => 'transfer',
        ]);
    }

    public function test_transfer_rows_with_cross_currency_accounts_are_marked_as_error(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $walletAccount = Account::factory()->for($user)->create([
            'name' => 'USD Wallet',
            'type' => 'other',
            'currency' => 'USD',
            'import_aliases' => ['USD Wallet Charge'],
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'USD Wallet Charge', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'row-cross-currency-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'error')
                ->where('rows.0.validation_errors.0', '振替元口座と振替先口座は同じ通貨である必要があります。'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'merchant_name' => 'USD Wallet Charge',
            'type' => 'transfer',
        ]);
    }

    public function test_transfer_rows_can_resolve_counterparty_when_same_name_candidates_are_disambiguated_by_currency(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $walletJpy = Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'type' => 'e_money',
            'currency' => 'USD',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'Wallet', '-1000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'wallet-transfer-jpy-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.status', 'ready')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.id', $walletJpy->id)
                ->where('rows.0.resolved_transfer_account.currency', 'JPY'));
    }

    public function test_reimported_transfer_row_is_shown_as_duplicate_candidate_by_money_forward_id(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-row-1'],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', 'transfer-row-1'],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'transfer')
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.is_duplicate_candidate', true));
    }

    public function test_transfer_mirror_duplicate_from_imported_rows_does_not_over_deduplicate_multiple_same_amount_pairs(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '三井住友カード引落A', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', ''],
                ['1', '2026/04/10', '三井住友カード引落B', '-5000', '住信SBIネット銀行', '未分類', '未分類', '', '1', ''],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '住信SBIネット銀行A', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'card-mirror-a'],
                ['1', '2026/04/10', '住信SBIネット銀行B', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'card-mirror-b'],
                ['1', '2026/04/10', '住信SBIネット銀行C', '-5000', '三井住友カード', '未分類', '未分類', '', '1', 'card-mirror-c'],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account.name', $bankAccount->name)
                ->where('rows.0.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.1.resolved_account.name', $bankAccount->name)
                ->where('rows.1.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.2.resolved_account.name', $bankAccount->name)
                ->where('rows.2.resolved_transfer_account.name', $cardAccount->name)
                ->where('rows.0.is_duplicate_candidate', true)
                ->where('rows.1.is_duplicate_candidate', true)
                ->where('rows.2.is_duplicate_candidate', false));
    }

    public function test_uncommitted_import_rows_do_not_block_later_reimport_as_duplicates(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $firstImport = Import::first();

        $this->assertDatabaseHas('imports', [
            'id' => $firstImport->id,
            'status' => 'validated',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', 'かっぽうぎ 天王洲店', '-990', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $secondImport = Import::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_duplicate_candidate', false)
                ->where('rows.0.status', 'ready'));
    }

    public function test_imported_import_cannot_be_committed_twice(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ]);

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->actingAs($user)
            ->from(route('imports.show', $import))
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas('error', 'すでに取込済みです。');
    }

    public function test_imported_import_cannot_be_reparsed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ]);

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->actingAs($user)
            ->post(route('imports.parse', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas('error', '取込済みの import は再解析できません。');

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'imported',
            'imported_rows' => 1,
        ]);
    }

    public function test_imported_import_can_be_deleted_with_its_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseMissing('imports', [
            'id' => $import->id,
        ]);
        $this->assertDatabaseMissing('import_rows', [
            'import_id' => $import->id,
        ]);
        $this->assertSoftDeleted('transactions', [
            'id' => $transaction->id,
            'import_id' => $import->id,
        ]);
    }

    public function test_import_deletion_also_removes_previously_soft_deleted_import_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();
        $transaction->delete();

        $this->assertSoftDeleted('transactions', [
            'id' => $transaction->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseMissing('imports', [
            'id' => $import->id,
        ]);
        $this->assertDatabaseMissing('import_rows', [
            'import_id' => $import->id,
        ]);
        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
            'import_id' => $import->id,
        ]);
    }

    public function test_deleting_one_import_does_not_delete_transactions_from_another_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $firstPayload = [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ];
        $secondPayload = [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/11', '別店舗', '-2000', 'd払い', '未分類', '未分類', '', '0', 'row-2'],
            ]),
        ];

        $this->actingAs($user)->post(route('imports.store'), $firstPayload)->assertRedirect();
        $firstImport = Import::first();
        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertRedirect(route('imports.show', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), $secondPayload)->assertRedirect();
        $secondImport = Import::query()->latest('id')->firstOrFail();
        $this->actingAs($user)
            ->post(route('imports.commit', $secondImport))
            ->assertRedirect(route('imports.show', $secondImport));

        $firstTransaction = Transaction::query()->where('import_id', $firstImport->id)->firstOrFail();
        $secondTransaction = Transaction::query()->where('import_id', $secondImport->id)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('imports.destroy', $firstImport))
            ->assertRedirect(route('imports.index'));

        $this->assertSoftDeleted('transactions', [
            'id' => $firstTransaction->id,
            'import_id' => $firstImport->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $secondTransaction->id,
            'import_id' => $secondImport->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_import_with_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->post(route('imports.store'), [
                'source_name' => 'money_forward',
                'account_id' => $otherAccount->id,
                'csv_file' => $this->moneyForwardUpload([
                    ['1', '2026/04/10', '店舗', '-1000', 'd払い', '未分類', '未分類', '', '0', 'row-1'],
                ]),
            ])
            ->assertSessionHasErrors('account_id');
    }

    public function test_ambiguous_account_name_is_not_auto_resolved(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'currency' => 'USD',
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => '',
            'csv_file' => $this->moneyForwardUpload([
                ['1', '2026/04/10', '店舗', '-1000', 'Wallet', '未分類', '未分類', '', '0', 'row-1'],
            ]),
        ])->assertRedirect();

        $import = Import::first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_account', null)
                ->where('rows.0.status', 'error')
                ->where('rows.0.validation_errors.0', '同名の口座が複数あるため取込先口座を特定できません。共通適用口座を選択してください。'));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function moneyForwardUpload(array $rows, ?array $headers = null, bool $convertToSjis = true): UploadedFile
    {
        $csvRows = [
            $headers ?? ['計算対象', '日付', '内容', '金額（円）', '保有金融機関', '大項目', '中項目', 'メモ', '振替', 'ID'],
            ...$rows,
        ];

        $contents = implode("\n", array_map(
            fn (array $row): string => implode(',', $row),
            $csvRows,
        ));

        return UploadedFile::fake()->createWithContent(
            'money_forward.csv',
            $convertToSjis ? mb_convert_encoding($contents, 'SJIS-win', 'UTF-8') : $contents,
        );
    }
}
