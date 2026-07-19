import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { ClassificationRuleListItem } from '@/types/classification-rule';
import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type IndexProps = {
    classificationRules: ClassificationRuleListItem[];
};

export default function Index({ classificationRules }: IndexProps) {
    const { t } = useTranslation('classificationRules');

    const handleDelete = (classificationRule: ClassificationRuleListItem) => {
        if (
            !window.confirm(
                t('index.confirmDelete', { name: classificationRule.name }),
            )
        ) {
            return;
        }

        router.delete(
            route('classification-rules.destroy', classificationRule.id),
        );
    };

    return (
        <AppPage title={t('index.title')} description={t('index.description')}>
            <div className="space-y-6">
                <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                    <p className="font-semibold">{t('index.usageTitle')}</p>
                    <ul className="mt-2 space-y-1 text-sky-800">
                        <li>{t('index.usage.previewOnly')}</li>
                        <li>{t('index.usage.csvFirst')}</li>
                        <li>{t('index.usage.fillMissing')}</li>
                        <li>{t('index.usage.firstMatch')}</li>
                        <li>{t('index.usage.noManual')}</li>
                    </ul>
                </div>

                <div className="flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">
                        {t('index.addHint')}
                    </p>

                    <Link
                        href={route('transactions.category-review.index')}
                        className="inline-flex shrink-0 items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.categoryReview')}
                    </Link>
                </div>

                {classificationRules.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            {t('index.empty')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">
                                            {t('table.name')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.transactionType')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.field')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.condition')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.category')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.subcategory')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.calculation')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.priority')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.status')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('table.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    {classificationRules.map(
                                        (classificationRule) => (
                                            <tr key={classificationRule.id}>
                                                <td className="px-4 py-4 font-medium text-slate-900">
                                                    {classificationRule.name}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {
                                                        classificationRule.transaction_type_label
                                                    }
                                                </td>
                                                <td className="px-4 py-4">
                                                    {
                                                        classificationRule.match_field_label
                                                    }
                                                </td>
                                                <td className="px-4 py-4">
                                                    {
                                                        classificationRule.match_operator_label
                                                    }
                                                    :{' '}
                                                    <span className="font-medium text-slate-900">
                                                        {
                                                            classificationRule.match_value
                                                        }
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {classificationRule.category
                                                        ?.name ?? '-'}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {classificationRule
                                                        .subcategory?.name ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {classificationRule.is_calculation_target ===
                                                    null
                                                        ? '-'
                                                        : classificationRule.is_calculation_target
                                                          ? t('status.included')
                                                          : t(
                                                                'status.excluded',
                                                            )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {
                                                        classificationRule.priority
                                                    }
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                            classificationRule.is_active
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : 'bg-slate-200 text-slate-600'
                                                        }`}
                                                    >
                                                        {classificationRule.is_active
                                                            ? t('status.active')
                                                            : t(
                                                                  'status.inactive',
                                                              )}
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
                                                            {t('actions.edit')}
                                                        </Link>
                                                        <DangerButton
                                                            type="button"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    classificationRule,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'actions.delete',
                                                            )}
                                                        </DangerButton>
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppPage>
    );
}
