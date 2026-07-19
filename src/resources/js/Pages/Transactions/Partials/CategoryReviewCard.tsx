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
import { useTranslation } from 'react-i18next';

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
    const { t } = useTranslation('transactions');
    const ruleSources = [
        {
            value: 'merchant_name' as const,
            label: t('categoryReview.card.merchant'),
            source: suggestion.merchant_name ?? '',
        },
        {
            value: 'description' as const,
            label: t('categoryReview.card.description'),
            source: suggestion.description ?? '',
        },
        {
            value: 'account_name' as const,
            label: t('categoryReview.card.account'),
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
                        <span>
                            {suggestion.type === 'expense'
                                ? t('categoryReview.card.expense')
                                : t('categoryReview.card.income')}
                        </span>
                        <span aria-hidden="true">·</span>
                        <span>
                            {suggestion.account_name ??
                                t('categoryReview.card.noAccount')}
                        </span>
                        <span
                            className={`ml-auto inline-flex rounded-full px-2.5 py-1 ${confidenceClass}`}
                        >
                            {suggestion.confidence > 0
                                ? t('categoryReview.card.confidence', {
                                      confidence: suggestion.confidence,
                                  })
                                : t('categoryReview.card.noSuggestion')}
                        </span>
                    </div>

                    <div className="mt-3 flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="break-words text-lg font-semibold text-slate-900">
                                {suggestion.merchant_name ??
                                    suggestion.description ??
                                    t('categoryReview.card.noDescription')}
                            </h2>
                            {suggestion.description &&
                                suggestion.description !==
                                    suggestion.merchant_name && (
                                    <p className="mt-1 break-words text-sm text-slate-600">
                                        {suggestion.description}
                                    </p>
                                )}
                        </div>
                        <p className="shrink-0 text-lg font-semibold text-slate-900">
                            {formatMoney(
                                suggestion.amount,
                                suggestion.currency,
                            )}{' '}
                            <span className="text-xs font-medium text-slate-500">
                                {suggestion.currency}
                            </span>
                        </p>
                    </div>

                    {(suggestion.payment_method_label || suggestion.memo) && (
                        <dl className="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-2">
                            {suggestion.payment_method_label && (
                                <div>
                                    <dt className="text-xs text-slate-500">
                                        {t('categoryReview.card.paymentMethod')}
                                    </dt>
                                    <dd className="mt-0.5 text-slate-700">
                                        {suggestion.payment_method_label}
                                    </dd>
                                </div>
                            )}
                            {suggestion.memo && (
                                <div>
                                    <dt className="text-xs text-slate-500">
                                        {t('categoryReview.card.memo')}
                                    </dt>
                                    <dd className="mt-0.5 text-slate-700">
                                        {suggestion.memo}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}

                    <div className="mt-4 rounded-xl border border-slate-200 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('categoryReview.card.reason')}
                        </p>
                        <p className="mt-1 text-sm text-slate-700">
                            {suggestion.reason}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-3 text-xs">
                            {suggestion.reference_count > 0 && (
                                <span className="text-slate-500">
                                    {t('categoryReview.card.matchingHistory', {
                                        count: suggestion.reference_count,
                                    })}
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
                                    {t('categoryReview.card.viewReference')}
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
                                    {t('categoryReview.card.viewRule')}
                                </Link>
                            )}
                            <Link
                                href={route(
                                    'transactions.show',
                                    suggestion.transaction_id,
                                )}
                                className="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                {t('categoryReview.card.viewTransaction')}
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
                                {t('categoryReview.card.confirmCategory')}
                            </p>
                            <p className="mt-1 text-sm text-slate-600">
                                {t('categoryReview.card.confirmHint')}
                            </p>
                        </div>
                        {suggestion.suggested_category && (
                            <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 shadow-sm">
                                {t('categoryReview.card.suggestionApplied')}
                            </span>
                        )}
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor={`category-${suggestion.transaction_id}`}
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('categoryReview.card.category')}
                        </label>
                        <select
                            id={`category-${suggestion.transaction_id}`}
                            value={data.category_id}
                            onChange={(event) =>
                                changeCategory(event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                {t('categoryReview.card.choose')}
                            </option>
                            {availableCategories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                    {!category.is_active
                                        ? t('categoryReview.card.inactive')
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.category_id}
                            className="mt-2"
                        />
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
                                {t('categoryReview.card.addCategory')}
                            </Link>
                            <Link
                                href={route('categories.index')}
                                className="font-medium text-slate-500 hover:text-slate-700"
                            >
                                {t('categoryReview.card.manageCategories')}
                            </Link>
                        </div>
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor={`subcategory-${suggestion.transaction_id}`}
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('categoryReview.card.subcategory')}
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
                            <option value="">
                                {t('categoryReview.card.none')}
                            </option>
                            {availableSubcategories.map((subcategory) => (
                                <option
                                    key={subcategory.id}
                                    value={subcategory.id}
                                >
                                    {subcategory.name}
                                    {!subcategory.is_active
                                        ? t('categoryReview.card.inactive')
                                        : ''}
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
                            {t('categoryReview.card.existingRule')}
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
                                        setData(
                                            'create_rule',
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-800">
                                        {t('categoryReview.card.autoClassify')}
                                    </span>
                                    <span className="mt-0.5 block text-xs leading-5 text-slate-500">
                                        {t(
                                            'categoryReview.card.autoClassifyHint',
                                        )}
                                    </span>
                                </span>
                            </label>
                            <InputError
                                message={errors.create_rule}
                                className="mt-2"
                            />

                            {data.create_rule && (
                                <div className="mt-3 space-y-3 border-t border-slate-200 pt-3">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label
                                                htmlFor={`rule-field-${suggestion.transaction_id}`}
                                                className="text-xs font-medium text-slate-600"
                                            >
                                                {t(
                                                    'categoryReview.card.matchField',
                                                )}
                                            </label>
                                            <select
                                                id={`rule-field-${suggestion.transaction_id}`}
                                                value={data.rule_match_field}
                                                onChange={(event) =>
                                                    changeRuleField(
                                                        event.target
                                                            .value as CategoryReviewForm['rule_match_field'],
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                {ruleSources.map((source) => (
                                                    <option
                                                        key={source.value}
                                                        value={source.value}
                                                    >
                                                        {source.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.rule_match_field
                                                }
                                                className="mt-2"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                htmlFor={`rule-operator-${suggestion.transaction_id}`}
                                                className="text-xs font-medium text-slate-600"
                                            >
                                                {t(
                                                    'categoryReview.card.matchOperator',
                                                )}
                                            </label>
                                            <select
                                                id={`rule-operator-${suggestion.transaction_id}`}
                                                value={data.rule_match_operator}
                                                onChange={(event) =>
                                                    setData(
                                                        'rule_match_operator',
                                                        event.target
                                                            .value as CategoryReviewForm['rule_match_operator'],
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="equals">
                                                    {t(
                                                        'categoryReview.card.exactRecommended',
                                                    )}
                                                </option>
                                                <option value="contains">
                                                    {t(
                                                        'categoryReview.card.contains',
                                                    )}
                                                </option>
                                                <option value="starts_with">
                                                    {t(
                                                        'categoryReview.card.startsWith',
                                                    )}
                                                </option>
                                            </select>
                                            <InputError
                                                message={
                                                    errors.rule_match_operator
                                                }
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor={`rule-value-${suggestion.transaction_id}`}
                                            className="text-xs font-medium text-slate-600"
                                        >
                                            {t(
                                                'categoryReview.card.matchValue',
                                            )}
                                        </label>
                                        <input
                                            id={`rule-value-${suggestion.transaction_id}`}
                                            type="text"
                                            value={data.rule_match_value}
                                            onChange={(event) =>
                                                setData(
                                                    'rule_match_value',
                                                    event.target.value,
                                                )
                                            }
                                            className="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <InputError
                                            message={errors.rule_match_value}
                                            className="mt-2"
                                        />
                                    </div>

                                    <p className="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                                        {t('categoryReview.card.rulePreview', {
                                            type:
                                                suggestion.type === 'expense'
                                                    ? t(
                                                          'categoryReview.card.expense',
                                                      )
                                                    : t(
                                                          'categoryReview.card.income',
                                                      ),
                                            field:
                                                ruleSources.find(
                                                    (source) =>
                                                        source.value ===
                                                        data.rule_match_field,
                                                )?.label ??
                                                t(
                                                    'categoryReview.card.details',
                                                ),
                                            value: data.rule_match_value,
                                            operator:
                                                data.rule_match_operator ===
                                                'equals'
                                                    ? t(
                                                          'categoryReview.card.exactPhrase',
                                                      )
                                                    : data.rule_match_operator ===
                                                        'contains'
                                                      ? t(
                                                            'categoryReview.card.containsPhrase',
                                                        )
                                                      : t(
                                                            'categoryReview.card.startsWithPhrase',
                                                        ),
                                            category:
                                                selectedCategory?.name ??
                                                t(
                                                    'categoryReview.card.selectedCategory',
                                                ),
                                            subcategory: selectedSubcategory
                                                ? ` / ${selectedSubcategory.name}`
                                                : '',
                                        })}
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="mt-4 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                            {t('categoryReview.card.ruleUnavailable')}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={processing || data.category_id === ''}
                        className="mt-5 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? t('categoryReview.card.saving')
                            : data.create_rule
                              ? t('categoryReview.card.saveWithRule')
                              : t('categoryReview.card.save')}
                    </button>
                    <p className="mt-2 text-center text-xs text-slate-500">
                        {t('categoryReview.card.unchangedHint')}
                    </p>
                </form>
            </div>
        </article>
    );
}
