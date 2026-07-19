<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Imports\BalanceSnapshotJsonParser;
use App\Services\Imports\JrePointJsonParser;
use App\Services\Imports\MobileSuicaPdfParser;
use App\Services\Imports\MoneyForwardAssetHistoryCsvParser;
use App\Services\Imports\MoneyForwardCsvParser;
use App\Services\Imports\PdfTextExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_japanese_is_the_default_locale(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ja')
                ->where('supported_locales', ['ja', 'en']));
    }

    public function test_locale_can_be_changed_and_is_kept_in_the_session(): void
    {
        $this->from('/login')
            ->put(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect('/login');

        $this->assertEquals('en', session('locale'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->withSession(['locale' => 'ja'])
            ->from('/login')
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('locale');

        $this->assertEquals('ja', session('locale'));
    }

    public function test_authentication_error_uses_the_selected_locale(): void
    {
        $this->withSession(['locale' => 'ja'])
            ->post('/login', [
                'email' => 'nobody@example.test',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors([
                'email' => __('auth.failed', locale: 'ja'),
            ]);

        $this->withSession(['locale' => 'en'])
            ->post('/login', [
                'email' => 'nobody@example.test',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors([
                'email' => __('auth.failed', locale: 'en'),
            ]);
    }

    public function test_standard_validation_messages_are_available_in_both_locales(): void
    {
        $this->assertSame(
            '利用規約には、\'true\'か\'false\'を指定してください。',
            __('validation.boolean', ['attribute' => '利用規約'], 'ja'),
        );
        $this->assertSame(
            'The terms field must be true or false.',
            __('validation.boolean', ['attribute' => 'terms'], 'en'),
        );
    }

    public function test_account_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.id', $account->id)
                ->where('accounts.0.type_label', 'Bank Account')
                ->where('accounts.0.balance_role_label', 'Asset')
                ->where('accounts.0.balance_method_label', 'Calculate from Ledger'));

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->get(route('accounts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeOptions.1.label', '銀行口座')
                ->where('balanceRoleOptions.0.label', '資産')
                ->where('balanceMethodOptions.0.label', '取引台帳から計算'));
    }

    public function test_transaction_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeOptions.0.label', 'Income')
                ->where('typeOptions.1.label', 'Expense')
                ->where('typeOptions.2.label', 'Transfer'));

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeOptions.0.label', '収入')
                ->where('typeOptions.1.label', '支出')
                ->where('typeOptions.2.label', '振替'));
    }

    public function test_transaction_validation_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'monthly_close_required' => true,
        ]);
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $payload = [
            'transaction_date' => '2026-07-19',
            'type' => 'expense',
            'account_id' => $account->id,
            'amount' => '1000',
            'currency' => 'USD',
            'category_id' => $category->id,
            'is_confirmed' => true,
            'is_calculation_target' => true,
            'affects_account_balance' => true,
        ];

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors([
                'currency' => __('transactions.messages.account_currency_mismatch', locale: 'en'),
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors([
                'currency' => __('transactions.messages.account_currency_mismatch', locale: 'ja'),
            ]);
    }

    public function test_classification_rule_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('classification-rules.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactionTypeOptions.0.label', 'All')
                ->where('matchFieldOptions.0.label', 'Description / Merchant')
                ->where('matchOperatorOptions.0.label', 'Contains'));

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->get(route('classification-rules.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactionTypeOptions.0.label', 'すべて')
                ->where('matchFieldOptions.0.label', '摘要 / 店舗名')
                ->where('matchOperatorOptions.0.label', '含む'));
    }

    public function test_import_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $mobileSuica = Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
        ]);
        $jrePoint = Account::factory()->for($user)->create([
            'name' => 'JRE ポイント',
            'type' => 'point',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('imports.create', ['source' => 'mobile_suica']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sourceOptions.1.label', 'Mobile Suica PDF')
                ->where('sourceOptions.3.label', 'Official Balances & Valuations')
                ->where('suggestedAccountIds.mobile_suica', $mobileSuica->id)
                ->where('suggestedAccountIds.jre_point', $jrePoint->id));

        $import = Import::create([
            'user_id' => $user->id,
            'account_id' => null,
            'source_name' => 'asset_history',
            'original_filename' => 'history.csv',
            'storage_path' => 'imports/test/history.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('imports.data.0.id', $import->id)
                ->where('imports.data.0.source_label', 'Money Forward Asset History')
                ->where('imports.data.0.status_label', 'Ready for Review'));
    }

    public function test_import_validation_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $payload = [
            'source_name' => 'jre_point',
            'csv_file' => UploadedFile::fake()->create('jre-point.json', 1, 'application/json'),
        ];

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('imports.store'), $payload)
            ->assertSessionHasErrors([
                'account_id' => __('imports.messages.jre_point_account_required', locale: 'en'),
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->post(route('imports.store'), $payload)
            ->assertSessionHasErrors([
                'account_id' => __('imports.messages.jre_point_account_required', locale: 'ja'),
            ]);
    }

    public function test_import_failure_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('imports.store'), [
                'source_name' => 'money_forward',
                'account_id' => '',
                'csv_file' => UploadedFile::fake()->createWithContent(
                    'invalid.csv',
                    "foo,bar\n1,2\n",
                ),
            ])
            ->assertRedirect();

        $import = Import::query()->firstOrFail();

        self::assertSame('failed', $import->status);
        self::assertSame(
            'Required Money Forward CSV headers are missing.',
            $import->error_message,
        );

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'import.error_message',
                    'Required Money Forward CSV headers are missing.',
                ));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->from(route('imports.show', $import))
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas(
                'error',
                'Only an import that has completed Preview can be applied.',
            );
    }

    public function test_source_specific_parse_errors_are_available_in_english(): void
    {
        app()->setLocale('en');

        self::assertSame(
            'The JRE POINT export JSON could not be read.',
            $this->runtimeExceptionMessage(
                fn () => app(JrePointJsonParser::class)->parse('{'),
            ),
        );
        self::assertSame(
            'The balance export JSON could not be read.',
            $this->runtimeExceptionMessage(
                fn () => app(BalanceSnapshotJsonParser::class)->parse('{'),
            ),
        );
        self::assertSame(
            'Required Money Forward asset history CSV headers are missing.',
            $this->runtimeExceptionMessage(
                fn () => app(MoneyForwardAssetHistoryCsvParser::class)->parse("foo\n"),
            ),
        );
        self::assertSame(
            'Required Money Forward CSV headers are missing.',
            $this->runtimeExceptionMessage(
                fn () => app(MoneyForwardCsvParser::class)->parse("foo\n"),
            ),
        );
        self::assertSame(
            'The PDF was not recognized as a Mobile Suica statement.',
            $this->runtimeExceptionMessage(
                fn () => app(MobileSuicaPdfParser::class)->parseExtractedText('invalid'),
            ),
        );
        self::assertSame(
            'The file was not recognized as a PDF.',
            $this->runtimeExceptionMessage(
                fn () => app(PdfTextExtractorService::class)->extract('invalid'),
            ),
        );
    }

    public function test_import_preview_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->create([
            'name' => 'Source Bank',
            'type' => 'bank',
        ]);
        $import = Import::create([
            'user_id' => $user->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'transfers.csv',
            'storage_path' => 'imports/test/transfers.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);
        $row = ImportRow::create([
            'import_id' => $import->id,
            'row_number' => 2,
            'raw_payload' => [],
            'detected_type' => 'transfer',
            'resolved_account_id' => $sourceAccount->id,
            'validation_errors' => ['振替先口座を特定できません。'],
            'transfer_resolution' => [
                'source_resolution_type' => 'account_name',
                'source_resolution_message' => '振替元口座は口座名一致で解決しました。',
                'destination_resolution_type' => 'unresolved',
                'destination_resolution_message' => '摘要 / 説明 / メモから振替先口座を特定できませんでした。',
                'unresolved_reason' => '振替先口座を特定できません。',
            ],
            'status' => 'error',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('imports.preview', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.validation_errors.0', 'The transfer destination account could not be identified.')
                ->where('rows.0.transfer_resolution.source_resolution_message', 'The transfer source was resolved by account name.')
                ->where('rows.0.transfer_resolution.destination_resolution_message', 'The transfer destination could not be identified from the description, details, or memo.')
                ->where('rows.0.transfer_resolution.unresolved_reason', 'The transfer destination account could not be identified.'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('imports.rows.update-transfer-account', [$import, $row]), [
                'resolved_transfer_account_id' => 'invalid',
            ])
            ->assertSessionHasErrors([
                "resolved_transfer_account_id.{$row->id}" => __('imports.messages.transfer_account_invalid', locale: 'en'),
            ]);
    }

    private function runtimeExceptionMessage(callable $callback): string
    {
        try {
            $callback();
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        self::fail('Expected a RuntimeException.');
    }

    public function test_dashboard_period_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'JPY']);

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-07-10',
            'type' => 'expense',
            'amount' => '1000.00',
            'currency' => 'JPY',
            'category_id' => null,
            'merchant_name' => null,
            'description' => null,
            'is_calculation_target' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['view' => 'month', 'month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_period_label', 'July 2026')
                ->where('month_options.0.label', 'January')
                ->where('monthly_trends.5.label', 'July 2026')
                ->where('monthly_report.comparison_groups.0.previous_month.label', 'June 2026')
                ->where('monthly_report.comparison_groups.0.previous_year.label', 'July 2025')
                ->where('monthly_report.category_expense_groups.0.previous_month_label', 'June 2026')
                ->where('monthly_report.category_expense_groups.0.items.0.category_name', 'Uncategorized')
                ->where('monthly_report.top_merchants.0.name', 'Unnamed')
                ->where('monthly_report.closing.status_label', 'Open')
                ->where('monthly_report.closing.blockers.0', 'The selected month has not ended yet.'));

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->get(route('dashboard', ['view' => 'month', 'month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_period_label', '2026年7月')
                ->where('month_options.0.label', '1月')
                ->where('monthly_trends.5.label', '2026年7月')
                ->where('monthly_report.comparison_groups.0.previous_month.label', '2026年6月')
                ->where('monthly_report.category_expense_groups.0.previous_month_label', '2026年6月')
                ->where('monthly_report.category_expense_groups.0.items.0.category_name', 'カテゴリ未設定')
                ->where('monthly_report.top_merchants.0.name', '名称なし')
                ->where('monthly_report.closing.status_label', '受付中')
                ->where('monthly_report.closing.blockers.0', '対象月がまだ終了していません。'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_period_label', '2026')
                ->where('yearly_trends.6.label', 'July 2026')
                ->where('yearly_category_expenses.0.items.0.category_name', 'Uncategorized'));
    }

    public function test_monthly_closing_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $requiredAccount = Account::factory()->for($user)->create([
            'name' => 'Test Card',
            'monthly_close_required' => true,
            'is_active' => true,
        ]);
        $excludedAccount = Account::factory()->for($user)->create([
            'monthly_close_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $requiredAccount,
            ]))
            ->assertSessionHas('success', 'Month-end update confirmed for Test Card.');

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $excludedAccount,
            ]))
            ->assertSessionHasErrors([
                'account' => 'This account is not included in monthly close review.',
            ]);
    }

    public function test_category_review_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => 'No suggestion merchant',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('transactions.category-review.index', ['status' => 'manual']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'review.items.0.reason',
                    'No category suggestion. Review existing classifications or add a classification rule.',
                ));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $category->id,
                'create_rule' => false,
            ])
            ->assertSessionHas('success', 'Category assigned.');
    }

    public function test_classification_rule_validation_messages_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $payload = [
            'name' => 'Coffee',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'equals',
            'match_value' => 'Coffee shop',
            'priority' => 0,
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('classification-rules.store'), $payload)
            ->assertSessionHasErrors([
                'category_id' => __('classification_rules.messages.completion_required', locale: 'en'),
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->post(route('classification-rules.store'), $payload)
            ->assertSessionHasErrors([
                'category_id' => __('classification_rules.messages.completion_required', locale: 'ja'),
            ]);
    }

    public function test_category_labels_and_review_notice_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('categories.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeOptions.0.label', 'Income')
                ->where('typeOptions.1.label', 'Expense')
                ->where('typeOptions.2.label', 'Both'));

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->get(route('categories.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeOptions.0.label', '収入')
                ->where('typeOptions.1.label', '支出')
                ->where('typeOptions.2.label', '両方'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('categories.store'), [
                'name' => 'Rewards',
                'type' => 'income',
                'display_order' => 0,
                'is_active' => true,
                'return_to' => 'category-review',
                'review_status' => 'manual',
                'review_type' => 'income',
            ])
            ->assertSessionHas(
                'success',
                'Added category “Rewards”. Select it for the relevant transaction.',
            );
    }

    public function test_category_validation_attributes_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('categories.store'), [
                'name' => '',
                'type' => 'expense',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'name' => __('validation.required', [
                    'attribute' => __('categories.fields.name', locale: 'en'),
                ], 'en'),
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ja'])
            ->post(route('categories.store'), [
                'name' => '',
                'type' => 'expense',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'name' => __('validation.required', [
                    'attribute' => __('categories.fields.name', locale: 'ja'),
                ], 'ja'),
            ]);
    }
}
