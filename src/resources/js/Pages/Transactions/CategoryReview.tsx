import AppPage from '@/Components/AppPage';
import CategoryReviewCard from '@/Pages/Transactions/Partials/CategoryReviewCard';
import { PageProps } from '@/types';
import {
    TransactionCategoryOption,
    TransactionCategoryReview,
    TransactionCategoryReviewFilters,
    TransactionSubcategoryOption,
} from '@/types/transaction';
import { Link, router, usePage } from '@inertiajs/react';

type CategoryReviewProps = {
    review: TransactionCategoryReview;
    filters: TransactionCategoryReviewFilters;
    categoryOptions: TransactionCategoryOption[];
    subcategoryOptions: TransactionSubcategoryOption[];
};

const statusOptions: Array<{
    value: TransactionCategoryReviewFilters['status'];
    label: string;
}> = [
    { value: 'high', label: '90%以上' },
    { value: 'manual', label: 'その他' },
    { value: 'all', label: 'すべて' },
];

export default function CategoryReview({
    review,
    filters,
    categoryOptions,
    subcategoryOptions,
}: CategoryReviewProps) {
    const flashSuccess = usePage<PageProps>().props.flash.success;

    const changeFilters = (
        status: TransactionCategoryReviewFilters['status'],
        type: TransactionCategoryReviewFilters['type'],
    ) => {
        router.get(
            route('transactions.category-review.index'),
            { status, type },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <AppPage
            title="カテゴリ確認"
            description="カテゴリ未設定の取引を、提案の根拠を見ながら1件ずつ確定します。"
        >
            <div className="space-y-6">
                {flashSuccess && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {flashSuccess}
                    </div>
                )}

                <div className="flex flex-col gap-4 rounded-2xl border border-indigo-100 bg-indigo-50 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="font-semibold text-indigo-950">
                            提案は自動適用されません
                        </p>
                        <p className="mt-1 text-sm leading-6 text-indigo-800/80">
                            90%以上を優先し、取引内容と根拠を確認してから確定してください。
                        </p>
                    </div>
                    <Link
                        href={route('transactions.index')}
                        className="inline-flex shrink-0 items-center justify-center rounded-md border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100"
                    >
                        取引一覧へ
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p className="text-sm text-slate-500">カテゴリ未設定</p>
                        <p className="mt-1 text-2xl font-semibold text-slate-900">
                            {review.summary.total}
                            <span className="ml-1 text-sm font-medium text-slate-500">件</span>
                        </p>
                    </div>
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p className="text-sm text-emerald-700">信頼度90%以上</p>
                        <p className="mt-1 text-2xl font-semibold text-emerald-900">
                            {review.summary.high_confidence}
                            <span className="ml-1 text-sm font-medium text-emerald-700">件</span>
                        </p>
                    </div>
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p className="text-sm text-amber-700">その他・提案なし</p>
                        <p className="mt-1 text-2xl font-semibold text-amber-900">
                            {review.summary.manual_review}
                            <span className="ml-1 text-sm font-medium text-amber-700">件</span>
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            表示対象
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {statusOptions.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() =>
                                        changeFilters(option.value, filters.type)
                                    }
                                    className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                                        filters.status === option.value
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="sm:w-48">
                        <label
                            htmlFor="review-type"
                            className="text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            取引種別
                        </label>
                        <select
                            id="review-type"
                            value={filters.type}
                            onChange={(event) =>
                                changeFilters(
                                    filters.status,
                                    event.target.value as TransactionCategoryReviewFilters['type'],
                                )
                            }
                            className="mt-2 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="all">収入・支出</option>
                            <option value="expense">支出のみ</option>
                            <option value="income">収入のみ</option>
                        </select>
                    </div>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {review.summary.displayed}件を表示
                    </p>
                    {review.has_more && (
                        <p className="text-xs text-slate-500">
                            先頭50件まで表示しています
                        </p>
                    )}
                </div>

                {review.items.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center">
                        <p className="font-medium text-slate-700">
                            この条件で確認する取引はありません。
                        </p>
                        <p className="mt-2 text-sm text-slate-500">
                            別の表示対象または取引種別を選んでください。
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {review.items.map((suggestion) => (
                            <CategoryReviewCard
                                key={suggestion.transaction_id}
                                suggestion={suggestion}
                                categoryOptions={categoryOptions}
                                subcategoryOptions={subcategoryOptions}
                                filters={filters}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppPage>
    );
}
