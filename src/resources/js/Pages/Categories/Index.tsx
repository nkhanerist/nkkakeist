import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { CategoryListItem } from '@/types/category';
import { Link, router } from '@inertiajs/react';

type IndexProps = {
    categories: CategoryListItem[];
};

export default function Index({ categories }: IndexProps) {
    const handleDelete = (category: CategoryListItem) => {
        if (! window.confirm(`「${category.name}」を削除しますか？`)) {
            return;
        }

        router.delete(route('categories.destroy', category.id));
    };

    return (
        <AppPage
            title="カテゴリ"
            description="カテゴリと小分類を管理できます。"
        >
            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">
                        ログインユーザーに紐づくカテゴリだけを表示しています。
                    </p>

                    <Link
                        href={route('categories.create')}
                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        カテゴリを追加
                    </Link>
                </div>

                {categories.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            まだカテゴリがありません。最初のカテゴリを追加してください。
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">カテゴリ名</th>
                                        <th className="px-4 py-3">種別</th>
                                        <th className="px-4 py-3">状態</th>
                                        <th className="px-4 py-3">表示順</th>
                                        <th className="px-4 py-3">小分類</th>
                                        <th className="px-4 py-3">操作</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    {categories.map((category) => (
                                        <tr key={category.id}>
                                            <td className="px-4 py-4">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        {category.color ? (
                                                            <span
                                                                className="inline-block h-3 w-3 rounded-full border border-slate-200"
                                                                style={{
                                                                    backgroundColor:
                                                                        category.color,
                                                                }}
                                                            />
                                                        ) : null}
                                                        <p className="font-medium text-slate-900">
                                                            {category.name}
                                                        </p>
                                                    </div>
                                                    {(category.icon ?? null) ? (
                                                        <p className="text-xs text-slate-500">
                                                            icon: {category.icon}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {category.type_label}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        category.is_active
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-slate-200 text-slate-600'
                                                    }`}
                                                >
                                                    {category.is_active
                                                        ? '有効'
                                                        : '無効'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                {category.display_order}
                                            </td>
                                            <td className="px-4 py-4">
                                                {category.subcategories.length === 0 ? (
                                                    <span className="text-xs text-slate-500">
                                                        未登録
                                                    </span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-2">
                                                        {category.subcategories.map(
                                                            (subcategory) => (
                                                                <span
                                                                    key={subcategory.id}
                                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                                        subcategory.is_active
                                                                            ? 'bg-slate-100 text-slate-700'
                                                                            : 'bg-slate-200 text-slate-500'
                                                                    }`}
                                                                >
                                                                    {subcategory.name}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={route(
                                                            'categories.edit',
                                                            category.id,
                                                        )}
                                                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    >
                                                        編集
                                                    </Link>
                                                    <DangerButton
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(category)
                                                        }
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
