import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {
    ClassificationRuleCategoryOption,
    ClassificationRuleListItem,
    ClassificationRuleOption,
    ClassificationRuleSubcategoryOption,
} from '@/types/classification-rule';
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

type ClassificationRuleFormProps = {
    classificationRule?: ClassificationRuleListItem;
    method: 'post' | 'put';
    submitRoute: string;
    submitLabel: string;
    transactionTypeOptions: ClassificationRuleOption[];
    matchFieldOptions: ClassificationRuleOption[];
    matchOperatorOptions: ClassificationRuleOption[];
    categoryOptions: ClassificationRuleCategoryOption[];
    subcategoryOptions: ClassificationRuleSubcategoryOption[];
};

type ClassificationRuleFormData = {
    name: string;
    transaction_type: string;
    match_field: string;
    match_operator: string;
    match_value: string;
    category_id: string;
    subcategory_id: string;
    is_calculation_target: string;
    priority: string;
    is_active: boolean;
};

export default function ClassificationRuleForm({
    classificationRule,
    method,
    submitRoute,
    submitLabel,
    transactionTypeOptions,
    matchFieldOptions,
    matchOperatorOptions,
    categoryOptions,
    subcategoryOptions,
}: ClassificationRuleFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<ClassificationRuleFormData>({
            name: classificationRule?.name ?? '',
            transaction_type: classificationRule?.transaction_type ?? 'any',
            match_field: classificationRule?.match_field ?? 'merchant_name',
            match_operator: classificationRule?.match_operator ?? 'contains',
            match_value: classificationRule?.match_value ?? '',
            category_id: classificationRule?.category?.id
                ? String(classificationRule.category.id)
                : '',
            subcategory_id: classificationRule?.subcategory?.id
                ? String(classificationRule.subcategory.id)
                : '',
            is_calculation_target:
                classificationRule?.is_calculation_target == null
                    ? ''
                    : classificationRule?.is_calculation_target
                      ? '1'
                      : '0',
            priority: String(classificationRule?.priority ?? 0),
            is_active: classificationRule?.is_active ?? true,
        });

    const filteredSubcategories = useMemo(
        () =>
            subcategoryOptions.filter(
                (subcategory) =>
                    data.category_id !== '' &&
                    subcategory.category_id === Number(data.category_id),
            ),
        [data.category_id, subcategoryOptions],
    );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (method === 'post') {
            post(submitRoute);

            return;
        }

        put(submitRoute);
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-6 rounded-xl border border-slate-200 bg-white p-6"
        >
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p className="font-semibold text-slate-900">適用ルール</p>
                <ol className="mt-2 space-y-1 list-decimal pl-5">
                    <li>まず CSV の大項目 / 中項目からカテゴリと小分類の解決を試みます。</li>
                    <li>
                        それで未解決だった項目だけ、このルールでカテゴリ / 小分類 /
                        集計対象フラグを補完します。
                    </li>
                    <li>
                        有効なルールを優先度の小さい順に評価し、最初に一致した1件だけを採用します。
                    </li>
                    <li>取引の手動入力画面には、まだ自動適用されません。</li>
                </ol>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value="ルール名" />
                    <TextInput
                        id="name"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        className="mt-1 block w-full"
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="priority" value="優先度" />
                    <TextInput
                        id="priority"
                        type="number"
                        min="0"
                        value={data.priority}
                        onChange={(event) => setData('priority', event.target.value)}
                        className="mt-1 block w-full"
                    />
                    <InputError message={errors.priority} className="mt-2" />
                </div>
            </div>

            <div className="rounded-xl border border-slate-200 p-5">
                <div className="mb-4">
                    <h3 className="text-sm font-semibold text-slate-900">一致条件</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        どの import row にこのルールを当てるかを設定します。
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="transaction_type" value="取引種別" />
                        <select
                            id="transaction_type"
                            value={data.transaction_type}
                            onChange={(event) => setData('transaction_type', event.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {transactionTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            指定した取引種別の import row だけを評価します。
                        </p>
                        <InputError message={errors.transaction_type} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="match_field" value="対象フィールド" />
                        <select
                            id="match_field"
                            value={data.match_field}
                            onChange={(event) => setData('match_field', event.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {matchFieldOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            import row のどの値を使って一致判定するかを選びます。
                        </p>
                        <InputError message={errors.match_field} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="match_operator" value="一致条件" />
                        <select
                            id="match_operator"
                            value={data.match_operator}
                            onChange={(event) => setData('match_operator', event.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {matchOperatorOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.match_operator} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="match_value" value="一致値" />
                        <TextInput
                            id="match_value"
                            value={data.match_value}
                            onChange={(event) => setData('match_value', event.target.value)}
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            ここに入力した文字列で一致判定します。
                        </p>
                        <InputError message={errors.match_value} className="mt-2" />
                    </div>
                </div>
            </div>

            <div className="rounded-xl border border-slate-200 p-5">
                <div className="mb-4">
                    <h3 className="text-sm font-semibold text-slate-900">補完内容</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        条件に一致した import row に対して、未解決の項目だけを補完します。
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="category_id" value="補完するカテゴリ" />
                        <select
                            id="category_id"
                            value={data.category_id}
                            onChange={(event) => {
                                const categoryId = event.target.value;
                                const nextSubcategories = subcategoryOptions.filter(
                                    (subcategory) =>
                                        categoryId !== '' &&
                                        subcategory.category_id === Number(categoryId),
                                );
                                setData('category_id', categoryId);

                                if (
                                    categoryId === '' ||
                                    !nextSubcategories.some(
                                        (subcategory) =>
                                            String(subcategory.id) === data.subcategory_id,
                                    )
                                ) {
                                    setData('subcategory_id', '');
                                }
                            }}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">補完しない</option>
                            {categoryOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.category_id} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="subcategory_id" value="補完する小分類" />
                        <select
                            id="subcategory_id"
                            value={data.subcategory_id}
                            onChange={(event) => setData('subcategory_id', event.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">補完しない</option>
                            {filteredSubcategories.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.subcategory_id} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="is_calculation_target"
                            value="補完する集計対象フラグ"
                        />
                        <select
                            id="is_calculation_target"
                            value={data.is_calculation_target}
                            onChange={(event) =>
                                setData('is_calculation_target', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">補完しない</option>
                            <option value="1">対象にする</option>
                            <option value="0">対象外にする</option>
                        </select>
                        <InputError
                            message={errors.is_calculation_target}
                            className="mt-2"
                        />
                    </div>

                    <div className="flex items-center gap-3 pt-8">
                        <input
                            id="is_active"
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) => setData('is_active', event.target.checked)}
                            className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <InputLabel htmlFor="is_active" value="有効にする" />
                        <InputError message={errors.is_active} className="mt-2" />
                    </div>
                </div>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
