import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

type AppPageProps = PropsWithChildren<{
    title: string;
    description: string;
}>;

export default function AppPage({
    title,
    description,
    children,
}: AppPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                </div>
            }
        >
            <Head title={title} />

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                {children}
            </div>
        </AuthenticatedLayout>
    );
}
