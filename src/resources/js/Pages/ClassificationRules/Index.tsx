import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { ClassificationRuleListItem } from '@/types/classification-rule';
import { Link, router } from '@inertiajs/react';

type IndexProps = {
    classificationRules: ClassificationRuleListItem[];
};

export default function Index({ classificationRules }: IndexProps) {
    const handleDelete = (classificationRule: ClassificationRuleListItem) => {
        if (! window.confirm(`「${classificationRule.name}」を削除しますか？`)) {
            return;
        }

        router.delete(route('classification-rules.destroy', classificationRule.id));
    };

    return (
        <AppPage
            title="分類ルール"
            description="インポートプレビューの自動分類ルールを管理できます。"
        >
            <div className="space-y-6">
                <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                    <p className="font-semibold">このルールが使われる場所</p>
                    <ul className="mt-2 space-y-1 text-sky-800">
                        <li>CSV インポートのプレビュー時にだけ使われます。</li>
                        <li>
                            まず Money Forward CSV の大項目 / 中項目でカテゴリ解決を試みます。
                        </li>
                        <li>
                            その結果、未解決だったカテゴリ / 小分類 / 集計対象フラグだけをルールで補完します。
                        </li>
                        <li>
                            有効なルールを優先度の小さい順に評価し、最初に一致した1件だけを採用します。
                        </li>
                        <li>取引の手動入力画面には、まだ自動適用されません。</li>
                    </ul>
                </div>

                <div className="flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">
                        ルールの追加は「カテゴリ確認」でカテゴリを確定するときに行います。ここでは編集・削除ができます。
                    </p>

                    <Link
                        href={route('transactions.category-review.index')}
                        className="inline-flex shrink-0 items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        カテゴリ確認へ
                    </Link>
                </div>

                {classificationRules.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            まだルールがありません。カテゴリ確認で取引を確定するときに作成できます。
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">ルール名</th>
                                        <th className="px-4 py-3">取引種別</th>
                                        <th className="px-4 py-3">対象</th>
                                        <th className="px-4 py-3">条件</th>
                                        <th className="px-4 py-3">カテゴリ</th>
                                        <th className="px-4 py-3">小分類</th>
                                        <th className="px-4 py-3">集計</th>
                                        <th className="px-4 py-3">優先度</th>
                                        <th className="px-4 py-3">状態</th>
                                        <th className="px-4 py-3">操作</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    {classificationRules.map((classificationRule) => (
                                        <tr key={classificationRule.id}>
                                            <td className="px-4 py-4 font-medium text-slate-900">
                                                {classificationRule.name}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.transaction_type_label}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.match_field_label}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.match_operator_label}:{' '}
                                                <span className="font-medium text-slate-900">
                                                    {classificationRule.match_value}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.category?.name ?? '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.subcategory?.name ?? '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.is_calculation_target === null
                                                    ? '-'
                                                    : classificationRule.is_calculation_target
                                                      ? '対象'
                                                      : '除外'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {classificationRule.priority}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        classificationRule.is_active
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-slate-200 text-slate-600'
                                                    }`}
                                                >
                                                    {classificationRule.is_active ? '有効' : '無効'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={route(
                                                            'classification-rules.edit',
                                                            classificationRule.id,
                                                        )}
                                                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    >
                                                        編集
                                                    </Link>
                                                    <DangerButton
                                                        type="button"
                                                        onClick={() => handleDelete(classificationRule)}
                                                    >
                                                        削除
                                                    </DangerButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppPage>
    );
}
