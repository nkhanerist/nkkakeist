import InputError from '@/Components/InputError';
import {
    TransactionCategoryOption,
    TransactionCategoryReviewFilters,
    TransactionCategorySuggestion,
    TransactionSubcategoryOption,
} from '@/types/transaction';
import { formatMoney } from '@/utils/currency';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type CategoryReviewCardProps = {
    suggestion: TransactionCategorySuggestion;
    categoryOptions: TransactionCategoryOption[];
    subcategoryOptions: TransactionSubcategoryOption[];
    filters: TransactionCategoryReviewFilters;
};

type CategoryReviewForm = {
    category_id: string;
    subcategory_id: string;
    create_rule: boolean;
    rule_match_field: 'merchant_name' | 'description' | 'account_name';
    rule_match_operator: 'equals' | 'contains' | 'starts_with';
    rule_match_value: string;
};

export default function CategoryReviewCard({
    suggestion,
    categoryOptions,
    subcategoryOptions,
    filters,
}: CategoryReviewCardProps) {
    const ruleSources = [
        {
            value: 'merchant_name' as const,
            label: '利用先・店舗名',
            source: suggestion.merchant_name ?? '',
        },
        {
            value: 'description' as const,
            label: '摘要',
            source: suggestion.description ?? '',
        },
        {
            value: 'account_name' as const,
            label: '口座名',
            source: suggestion.account_name ?? '',
        },
    ].filter((option) => option.source.trim() !== '');
    const defaultRuleSource = ruleSources[0];
    const alreadyHasRule = suggestion.matched_classification_rule_id !== null;
    const { data, setData, patch, processing, errors } =
        useForm<CategoryReviewForm>({
        category_id: suggestion.suggested_category_id?.toString() ?? '',
        subcategory_id:
            suggestion.suggested_subcategory_id?.toString() ?? '',
        create_rule: false,
        rule_match_field: defaultRuleSource?.value ?? 'merchant_name',
        rule_match_operator: 'equals',
        rule_match_value: defaultRuleSource?.source ?? '',
    });

    const availableCategories = categoryOptions.filter(
        (category) =>
            category.type === suggestion.type || category.type === 'both',
    );
    const availableSubcategories = subcategoryOptions.filter(
        (subcategory) =>
            subcategory.category_id.toString() === data.category_id,
    );
    const selectedCategory = availableCategories.find(
        (category) => category.id.toString() === data.category_id,
    );
    const selectedSubcategory = availableSubcategories.find(
        (subcategory) => subcategory.id.toString() === data.subcategory_id,
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        patch(
            route(
                'transactions.category-review.update',
                suggestion.transaction_id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    const changeCategory = (categoryId: string) => {
        setData((current) => ({
            ...current,
            category_id: categoryId,
            subcategory_id: '',
        }));
    };

    const changeRuleField = (field: CategoryReviewForm['rule_match_field']) => {
        const source = ruleSources.find((option) => option.value === field);

        setData((current) => ({
            ...current,
            rule_match_field: field,
            rule_match_value: source?.source ?? '',
        }));
    };

    const confidenceClass =
        suggestion.confidence >= 90
            ? 'bg-emerald-100 text-emerald-700'
            : suggestion.confidence > 0
              ? 'bg-amber-100 text-amber-700'
              : 'bg-slate-200 text-slate-600';

    return (
        <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="grid gap-5 p-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                        <span>{suggestion.transaction_date}</span>
                        <span aria-hidden="true">·</span>
                        <span>{suggestion.type === 'expense' ? '支出' : '収入'}</span>
                        <span aria-hidden="true">·</span>
                        <span>{suggestion.account_name ?? '口座なし'}</span>
                        <span
                            className={`ml-auto inline-flex rounded-full px-2.5 py-1 ${confidenceClass}`}
                        >
                            {suggestion.confidence > 0
                                ? `信頼度 ${suggestion.confidence}%`
                                : '提案なし'}
                        </span>
                    </div>

                    <div className="mt-3 flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="break-words text-lg font-semibold text-slate-900">
                                {suggestion.merchant_name ??
                                    suggestion.description ??
                                    '摘要なし'}
                            </h2>
                            {suggestion.description &&
                                suggestion.description !== suggestion.merchant_name && (
                                    <p className="mt-1 break-words text-sm text-slate-600">
                                        {suggestion.description}
                                    </p>
                                )}
                        </div>
                        <p className="shrink-0 text-lg font-semibold text-slate-900">
                            {formatMoney(suggestion.amount, suggestion.currency)}{' '}
                            <span className="text-xs font-medium text-slate-500">
                                {suggestion.currency}
                            </span>
                        </p>
                    </div>

                    {(suggestion.payment_method_label || suggestion.memo) && (
                        <dl className="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-2">
                            {suggestion.payment_method_label && (
                                <div>
                                    <dt className="text-xs text-slate-500">支払方法</dt>
                                    <dd className="mt-0.5 text-slate-700">
                                        {suggestion.payment_method_label}
                                    </dd>
                                </div>
                            )}
                            {suggestion.memo && (
                                <div>
                                    <dt className="text-xs text-slate-500">メモ</dt>
                                    <dd className="mt-0.5 text-slate-700">
                                        {suggestion.memo}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}

                    <div className="mt-4 rounded-xl border border-slate-200 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            提案の根拠
                        </p>
                        <p className="mt-1 text-sm text-slate-700">
                            {suggestion.reason}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-3 text-xs">
                            {suggestion.reference_count > 0 && (
                                <span className="text-slate-500">
                                    一致履歴 {suggestion.reference_count}件
                                </span>
                            )}
                            {suggestion.reference_transaction_id && (
                                <Link
                                    href={route(
                                        'transactions.show',
                                        suggestion.reference_transaction_id,
                                    )}
                                    className="font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    参照取引を見る
                                </Link>
                            )}
                            {suggestion.matched_classification_rule_id && (
                                <Link
                                    href={route(
                                        'classification-rules.edit',
                                        suggestion.matched_classification_rule_id,
                                    )}
                                    className="font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    分類ルールを見る
                                </Link>
                            )}
                            <Link
                                href={route(
                                    'transactions.show',
                                    suggestion.transaction_id,
                                )}
                                className="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                取引詳細を開く
                            </Link>
                        </div>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                                カテゴリを確定
                            </p>
                            <p className="mt-1 text-sm text-slate-600">
                                提案を確認し、必要なら選び直してください。
                            </p>
                        </div>
                        {suggestion.suggested_category && (
                            <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 shadow-sm">
                                提案入力済み
                            </span>
                        )}
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor={`category-${suggestion.transaction_id}`}
                            className="text-sm font-medium text-slate-700"
                        >
                            カテゴリ
                        </label>
                        <select
                            id={`category-${suggestion.transaction_id}`}
                            value={data.category_id}
                            onChange={(event) =>
                                changeCategory(event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">選択してください</option>
                            {availableCategories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                    {!category.is_active ? '（無効）' : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.category_id} className="mt-2" />
                        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <Link
                                href={route('categories.create', {
                                    type: suggestion.type,
                                    return_to: 'category-review',
                                    review_status: filters.status,
                                    review_type: filters.type,
                                })}
                                className="font-semibold text-indigo-700 hover:text-indigo-500"
                            >
                                ＋ カテゴリを追加
                            </Link>
                            <Link
                                href={route('categories.index')}
                                className="font-medium text-slate-500 hover:text-slate-700"
                            >
                                カテゴリ・小分類を管理
                            </Link>
                        </div>
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor={`subcategory-${suggestion.transaction_id}`}
                            className="text-sm font-medium text-slate-700"
                        >
                            小分類
                        </label>
                        <select
                            id={`subcategory-${suggestion.transaction_id}`}
                            value={data.subcategory_id}
                            disabled={data.category_id === ''}
                            onChange={(event) =>
                                setData('subcategory_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm disabled:bg-slate-100 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">指定なし</option>
                            {availableSubcategories.map((subcategory) => (
                                <option key={subcategory.id} value={subcategory.id}>
                                    {subcategory.name}
                                    {!subcategory.is_active ? '（無効）' : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.subcategory_id}
                            className="mt-2"
                        />
                    </div>

                    {alreadyHasRule ? (
                        <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs leading-5 text-emerald-800">
                            この提案には既存の分類ルールが使われています。確定時に重複ルールは作成しません。
                        </div>
                    ) : ruleSources.length > 0 ? (
                        <div className="mt-4 rounded-xl border border-indigo-200 bg-white p-3">
                            <label
                                htmlFor={`create-rule-${suggestion.transaction_id}`}
                                className="flex cursor-pointer items-start gap-3"
                            >
                                <input
                                    id={`create-rule-${suggestion.transaction_id}`}
                                    type="checkbox"
                                    checked={data.create_rule}
                                    onChange={(event) =>
                                        setData('create_rule', event.target.checked)
                                    }
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-800">
                                        今後も同じ明細を自動分類する
                                    </span>
                                    <span className="mt-0.5 block text-xs leading-5 text-slate-500">
                                        次回以降のCSV取込みで、未分類の明細に適用します。
                                    </span>
                                </span>
                            </label>
                            <InputError message={errors.create_rule} className="mt-2" />

                            {data.create_rule && (
                                <div className="mt-3 space-y-3 border-t border-slate-200 pt-3">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label
                                                htmlFor={`rule-field-${suggestion.transaction_id}`}
                                                className="text-xs font-medium text-slate-600"
                                            >
                                                判定に使う項目
                                            </label>
                                            <select
                                                id={`rule-field-${suggestion.transaction_id}`}
                                                value={data.rule_match_field}
                                                onChange={(event) =>
                                                    changeRuleField(
                                                        event.target.value as CategoryReviewForm['rule_match_field'],
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                {ruleSources.map((source) => (
                                                    <option key={source.value} value={source.value}>
                                                        {source.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.rule_match_field}
                                                className="mt-2"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                htmlFor={`rule-operator-${suggestion.transaction_id}`}
                                                className="text-xs font-medium text-slate-600"
                                            >
                                                一致方法
                                            </label>
                                            <select
                                                id={`rule-operator-${suggestion.transaction_id}`}
                                                value={data.rule_match_operator}
                                                onChange={(event) =>
                                                    setData(
                                                        'rule_match_operator',
                                                        event.target.value as CategoryReviewForm['rule_match_operator'],
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="equals">完全一致（推奨）</option>
                                                <option value="contains">含む</option>
                                                <option value="starts_with">前方一致</option>
                                            </select>
                                            <InputError
                                                message={errors.rule_match_operator}
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor={`rule-value-${suggestion.transaction_id}`}
                                            className="text-xs font-medium text-slate-600"
                                        >
                                            一致値
                                        </label>
                                        <input
                                            id={`rule-value-${suggestion.transaction_id}`}
                                            type="text"
                                            value={data.rule_match_value}
                                            onChange={(event) =>
                                                setData('rule_match_value', event.target.value)
                                            }
                                            className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <InputError
                                            message={errors.rule_match_value}
                                            className="mt-2"
                                        />
                                    </div>

                                    <p className="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                                        <span className="font-semibold text-slate-700">
                                            作成するルール：
                                        </span>
                                        {suggestion.type === 'expense' ? '支出' : '収入'}の
                                        {ruleSources.find(
                                            (source) => source.value === data.rule_match_field,
                                        )?.label ?? '明細'}
                                        が「{data.rule_match_value}」に
                                        {data.rule_match_operator === 'equals'
                                            ? '完全一致'
                                            : data.rule_match_operator === 'contains'
                                              ? 'を含む'
                                              : 'で始まる'}
                                        場合、{selectedCategory?.name ?? '選択したカテゴリ'}
                                        {selectedSubcategory
                                            ? ` / ${selectedSubcategory.name}`
                                            : ''}
                                        に分類
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="mt-4 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                            利用先・摘要・口座名がないため、この取引から分類ルールは作成できません。
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={processing || data.category_id === ''}
                        className="mt-5 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? '保存中…'
                            : data.create_rule
                              ? 'カテゴリ確定＋ルール作成'
                              : 'このカテゴリで確定'}
                    </button>
                    <p className="mt-2 text-center text-xs text-slate-500">
                        金額や確認状態など、他の項目は変更しません。
                    </p>
                </form>
            </div>
        </article>
    );
}
