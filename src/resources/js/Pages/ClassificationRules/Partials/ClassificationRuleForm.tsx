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
import { useTranslation } from 'react-i18next';

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
    const { t } = useTranslation('classificationRules');
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
                <p className="font-semibold text-slate-900">
                    {t('form.usageTitle')}
                </p>
                <ol className="mt-2 space-y-1 list-decimal pl-5">
                    <li>{t('form.usage.csvFirst')}</li>
                    <li>{t('form.usage.fillMissing')}</li>
                    <li>{t('form.usage.firstMatch')}</li>
                    <li>{t('form.usage.noManual')}</li>
                </ol>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value={t('form.name')} />
                    <TextInput
                        id="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        className="mt-1 block w-full"
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="priority" value={t('form.priority')} />
                    <TextInput
                        id="priority"
                        type="number"
                        min="0"
                        value={data.priority}
                        onChange={(event) =>
                            setData('priority', event.target.value)
                        }
                        className="mt-1 block w-full"
                    />
                    <InputError message={errors.priority} className="mt-2" />
                </div>
            </div>

            <div className="rounded-xl border border-slate-200 p-5">
                <div className="mb-4">
                    <h3 className="text-sm font-semibold text-slate-900">
                        {t('form.conditionTitle')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {t('form.conditionDescription')}
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="transaction_type"
                            value={t('form.transactionType')}
                        />
                        <select
                            id="transaction_type"
                            value={data.transaction_type}
                            onChange={(event) =>
                                setData('transaction_type', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {transactionTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('form.transactionTypeHint')}
                        </p>
                        <InputError
                            message={errors.transaction_type}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="match_field"
                            value={t('form.matchField')}
                        />
                        <select
                            id="match_field"
                            value={data.match_field}
                            onChange={(event) =>
                                setData('match_field', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {matchFieldOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('form.matchFieldHint')}
                        </p>
                        <InputError
                            message={errors.match_field}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="match_operator"
                            value={t('form.matchOperator')}
                        />
                        <select
                            id="match_operator"
                            value={data.match_operator}
                            onChange={(event) =>
                                setData('match_operator', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {matchOperatorOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.match_operator}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="match_value"
                            value={t('form.matchValue')}
                        />
                        <TextInput
                            id="match_value"
                            value={data.match_value}
                            onChange={(event) =>
                                setData('match_value', event.target.value)
                            }
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            {t('form.matchValueHint')}
                        </p>
                        <InputError
                            message={errors.match_value}
                            className="mt-2"
                        />
                    </div>
                </div>
            </div>

            <div className="rounded-xl border border-slate-200 p-5">
                <div className="mb-4">
                    <h3 className="text-sm font-semibold text-slate-900">
                        {t('form.completionTitle')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {t('form.completionDescription')}
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="category_id"
                            value={t('form.category')}
                        />
                        <select
                            id="category_id"
                            value={data.category_id}
                            onChange={(event) => {
                                const categoryId = event.target.value;
                                const nextSubcategories =
                                    subcategoryOptions.filter(
                                        (subcategory) =>
                                            categoryId !== '' &&
                                            subcategory.category_id ===
                                                Number(categoryId),
                                    );
                                setData('category_id', categoryId);

                                if (
                                    categoryId === '' ||
                                    !nextSubcategories.some(
                                        (subcategory) =>
                                            String(subcategory.id) ===
                                            data.subcategory_id,
                                    )
                                ) {
                                    setData('subcategory_id', '');
                                }
                            }}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{t('form.doNotFill')}</option>
                            {categoryOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.category_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="subcategory_id"
                            value={t('form.subcategory')}
                        />
                        <select
                            id="subcategory_id"
                            value={data.subcategory_id}
                            onChange={(event) =>
                                setData('subcategory_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{t('form.doNotFill')}</option>
                            {filteredSubcategories.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.subcategory_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="is_calculation_target"
                            value={t('form.calculationTarget')}
                        />
                        <select
                            id="is_calculation_target"
                            value={data.is_calculation_target}
                            onChange={(event) =>
                                setData(
                                    'is_calculation_target',
                                    event.target.value,
                                )
                            }
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{t('form.doNotFill')}</option>
                            <option value="1">{t('form.include')}</option>
                            <option value="0">{t('form.exclude')}</option>
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
                            onChange={(event) =>
                                setData('is_active', event.target.checked)
                            }
                            className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <InputLabel
                            htmlFor="is_active"
                            value={t('form.active')}
                        />
                        <InputError
                            message={errors.is_active}
                            className="mt-2"
                        />
                    </div>
                </div>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    {submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}
