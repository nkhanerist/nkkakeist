import AppPage from '@/Components/AppPage';
import { PaginatedImports } from '@/types/import';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type IndexProps = {
    imports: PaginatedImports;
};

export default function Index({ imports }: IndexProps) {
    const { t } = useTranslation('imports');

    return (
        <AppPage title={t('index.title')} description={t('index.description')}>
            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">{t('index.scope')}</p>

                    <Link
                        href={route('imports.create')}
                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.start')}
                    </Link>
                </div>

                {imports.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            {t('index.empty')}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-hidden rounded-xl border border-slate-200">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            <th className="px-4 py-3">
                                                {t('table.filename')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.source')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.status')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.totalRows')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.importedRows')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.duplicates')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.createdAt')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.importedAt')}
                                            </th>
                                            <th className="px-4 py-3">
                                                {t('table.actions')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                        {imports.data.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-4">
                                                    <div>
                                                        <p className="font-medium text-slate-900">
                                                            {
                                                                item.original_filename
                                                            }
                                                        </p>
                                                        {item.account ? (
                                                            <p className="mt-1 text-xs text-slate-500">
                                                                {t(
                                                                    'table.account',
                                                                    {
                                                                        name: item
                                                                            .account
                                                                            .name,
                                                                        currency:
                                                                            item
                                                                                .account
                                                                                .currency,
                                                                    },
                                                                )}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.source_label}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                        {item.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.total_rows}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.imported_rows}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.duplicate_rows}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.created_at ?? '-'}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.imported_at ?? '-'}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Link
                                                        href={route(
                                                            'imports.show',
                                                            item.id,
                                                        )}
                                                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    >
                                                        {t('actions.details')}
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {imports.last_page > 1 && (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-slate-500">
                                    {t('pagination.summary', {
                                        from: imports.from ?? 0,
                                        to: imports.to ?? 0,
                                        total: imports.total,
                                    })}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {imports.links.map((link, index) => (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={link.url ?? '#'}
                                            preserveState
                                            preserveScroll
                                            className={`rounded-md px-3 py-2 text-sm ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'border border-slate-300 bg-white text-slate-700'
                                            } ${link.url === null ? 'pointer-events-none opacity-50' : ''}`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppPage>
    );
}
