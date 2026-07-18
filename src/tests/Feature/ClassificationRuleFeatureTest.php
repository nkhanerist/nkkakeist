<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Import;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassificationRuleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_user_can_view_only_their_own_classification_rules(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownRule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => 'Starbucks',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'priority' => 1,
            'is_active' => true,
        ]);

        ClassificationRule::create([
            'user_id' => $otherUser->id,
            'name' => 'Other',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => '他人',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('classification-rules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClassificationRules/Index')
                ->has('classificationRules', 1)
                ->where('classificationRules.0.id', $ownRule->id)
                ->where('classificationRules.0.name', 'Starbucks'));
    }

    public function test_user_can_create_a_classification_rule(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => 'カフェ支出',
                'transaction_type' => 'expense',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => 'スタバ',
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
                'is_calculation_target' => false,
                'priority' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('classification-rules.index'));

        $this->assertDatabaseHas('classification_rules', [
            'user_id' => $user->id,
            'name' => 'カフェ支出',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_calculation_target' => 0,
            'priority' => 10,
            'is_active' => 1,
        ]);
    }

    public function test_user_can_edit_their_own_classification_rule(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();
        $classificationRule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '旧ルール',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => '旧',
            'priority' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('classification-rules.update', $classificationRule), [
                'name' => '新ルール',
                'transaction_type' => 'expense',
                'match_field' => 'description',
                'match_operator' => 'starts_with',
                'match_value' => 'ランチ',
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
                'is_calculation_target' => true,
                'priority' => 1,
                'is_active' => false,
            ])
            ->assertRedirect(route('classification-rules.index'));

        $this->assertDatabaseHas('classification_rules', [
            'id' => $classificationRule->id,
            'name' => '新ルール',
            'match_field' => 'description',
            'match_operator' => 'starts_with',
            'match_value' => 'ランチ',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_calculation_target' => 1,
            'priority' => 1,
            'is_active' => 0,
        ]);
    }

    public function test_user_can_delete_their_own_classification_rule(): void
    {
        $user = User::factory()->create();
        $classificationRule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '削除ルール',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => '削除',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('classification-rules.destroy', $classificationRule))
            ->assertRedirect(route('classification-rules.index'));

        $this->assertModelMissing($classificationRule);
    }

    public function test_user_cannot_access_or_modify_another_users_classification_rule(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $classificationRule = ClassificationRule::create([
            'user_id' => $otherUser->id,
            'name' => '他人ルール',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => '他人',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('classification-rules.edit', $classificationRule))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('classification-rules.update', $classificationRule), [
                'name' => 'blocked',
                'transaction_type' => 'expense',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => 'blocked',
                'category_id' => '',
                'subcategory_id' => '',
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('classification-rules.destroy', $classificationRule))
            ->assertForbidden();
    }

    public function test_category_and_unrelated_subcategory_combination_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $otherCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($otherCategory)->create();

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => '不正ルール',
                'transaction_type' => 'expense',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => '店',
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('subcategory_id');
    }

    public function test_other_users_category_and_subcategory_cannot_be_used_for_rule(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create(['type' => 'expense']);
        $otherSubcategory = Subcategory::factory()->forCategory($otherCategory)->create();

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => '他人カテゴリ',
                'transaction_type' => 'expense',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => '店',
                'category_id' => $otherCategory->id,
                'subcategory_id' => $otherSubcategory->id,
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['category_id', 'subcategory_id']);
    }

    public function test_rule_category_type_must_match_transaction_type(): void
    {
        $user = User::factory()->create();
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => '収入に支出カテゴリ',
                'transaction_type' => 'income',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => '給与',
                'category_id' => $expenseCategory->id,
                'subcategory_id' => '',
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_rule_with_any_transaction_type_requires_both_category(): void
    {
        $user = User::factory()->create();
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => 'すべてに支出カテゴリ',
                'transaction_type' => 'any',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => '共通',
                'category_id' => $expenseCategory->id,
                'subcategory_id' => '',
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_transfer_transaction_type_cannot_be_used_for_rule(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => '振替ルール',
                'transaction_type' => 'transfer',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => '振替',
                'category_id' => '',
                'subcategory_id' => '',
                'is_calculation_target' => '1',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('transaction_type');
    }

    public function test_rule_requires_at_least_one_effective_target(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('classification-rules.store'), [
                'name' => '条件だけルール',
                'transaction_type' => 'expense',
                'match_field' => 'merchant_name',
                'match_operator' => 'contains',
                'match_value' => 'スタバ',
                'category_id' => '',
                'subcategory_id' => '',
                'is_calculation_target' => '',
                'priority' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_priority_order_picks_the_first_matching_rule(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $highPriorityCategory = Category::factory()->for($user)->create([
            'name' => '優先カテゴリ',
            'type' => 'expense',
        ]);
        $lowPriorityCategory = Category::factory()->for($user)->create([
            'name' => '後順位カテゴリ',
            'type' => 'expense',
        ]);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '後順位',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $lowPriorityCategory->id,
            'priority' => 20,
            'is_active' => true,
        ]);

        $highPriorityRule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '最優先',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $highPriorityCategory->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            ['1', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_category.name', '優先カテゴリ')
                ->where('rows.0.matched_classification_rule.id', $highPriorityRule->id)
                ->where('rows.0.category_resolution_source', 'rule'));
    }

    public function test_inactive_rule_is_not_applied(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '無効ルール',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $category->id,
            'priority' => 1,
            'is_active' => false,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            ['1', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_category', null)
                ->where('rows.0.matched_classification_rule', null));
    }

    public function test_transaction_type_mismatch_rule_is_not_applied(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '収入専用',
            'transaction_type' => 'income',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $category->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            ['1', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.detected_type', 'expense')
                ->where('rows.0.resolved_category', null)
                ->where('rows.0.matched_classification_rule', null));
    }

    public function test_import_preview_rule_can_fill_category_subcategory_and_calculation_target(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->forCategory($category)->create([
            'name' => 'カフェ',
        ]);

        $rule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => 'スタバ分類',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_calculation_target' => false,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            [' ', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_category.name', '食費')
                ->where('rows.0.resolved_subcategory.name', 'カフェ')
                ->where('rows.0.is_calculation_target', false)
                ->where('rows.0.matched_classification_rule.id', $rule->id)
                ->where('rows.0.category_resolution_source', 'rule')
                ->where('rows.0.subcategory_resolution_source', 'rule')
                ->where('rows.0.calculation_target_source', 'rule'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_calculation_target' => 0,
        ]);
    }

    public function test_repreview_restores_original_calculation_target_after_rule_is_disabled(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $rule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => 'スタバ集計除外',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'is_calculation_target' => false,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            [' ', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_calculation_target', false)
                ->where('rows.0.calculation_target_source', 'rule'));

        $rule->update(['is_active' => false]);

        $this->actingAs($user)
            ->post(route('imports.parse', $import))
            ->assertRedirect(route('imports.preview', $import));

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.is_calculation_target', null)
                ->where('rows.0.calculation_target_source', null)
                ->where('rows.0.matched_classification_rule', null));
    }

    public function test_csv_resolved_category_and_subcategory_are_not_overwritten_by_rule(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $csvCategory = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $csvSubcategory = Subcategory::factory()->forCategory($csvCategory)->create([
            'name' => '外食',
        ]);
        $ruleCategory = Category::factory()->for($user)->create([
            'name' => '交際費',
            'type' => 'expense',
        ]);
        $ruleSubcategory = Subcategory::factory()->forCategory($ruleCategory)->create([
            'name' => 'カフェ',
        ]);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => 'スタバ分類',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $ruleCategory->id,
            'subcategory_id' => $ruleSubcategory->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            ['1', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '食費', '外食', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_category.name', '食費')
                ->where('rows.0.resolved_subcategory.name', '外食')
                ->where('rows.0.category_resolution_source', 'csv')
                ->where('rows.0.subcategory_resolution_source', 'csv')
                ->where('rows.0.matched_classification_rule', null));
    }

    public function test_rule_resolved_category_type_mismatch_is_marked_as_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => '給与',
            'type' => 'income',
        ]);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '不正な収入カテゴリ補完',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'スタバ',
            'category_id' => $incomeCategory->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $import = $this->createImportViaStore($user, $account, [
            ['1', '2026/04/10', 'スタバ 天王洲', '-990', 'd払い', '', '', '', '', 'row-1'],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.resolved_category.name', '給与')
                ->where('rows.0.status', 'error')
                ->has('rows.0.validation_errors', 1)
                ->where(
                    'rows.0.validation_errors.0',
                    '既存カテゴリの種別が取引種別と一致していません。',
                ));
    }

    private function createImportViaStore(User $user, Account $account, array $rows): Import
    {
        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'money_forward',
            'account_id' => $account->id,
            'csv_file' => $this->moneyForwardUpload($rows),
        ])->assertRedirect();

        return Import::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function moneyForwardUpload(array $rows): UploadedFile
    {
        $csvRows = [
            ['計算対象', '日付', '内容', '金額（円）', '保有金融機関', '大項目', '中項目', 'メモ', '振替', 'ID'],
            ...$rows,
        ];

        $contents = implode("\n", array_map(
            fn (array $row): string => implode(',', $row),
            $csvRows,
        ));

        return UploadedFile::fake()->createWithContent(
            'money_forward.csv',
            mb_convert_encoding($contents, 'SJIS-win', 'UTF-8'),
        );
    }
}
