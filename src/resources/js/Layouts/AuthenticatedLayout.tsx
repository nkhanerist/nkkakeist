import Dropdown from '@/Components/Dropdown';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

type NavigationItem = {
    label: string;
    href: string;
    active: boolean;
};

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const navigationItems: NavigationItem[] = [
        {
            label: 'ダッシュボード',
            href: route('dashboard'),
            active: route().current('dashboard'),
        },
        {
            label: '口座',
            href: route('accounts.index'),
            active: route().current('accounts.*'),
        },
        {
            label: 'カテゴリ',
            href: route('categories.index'),
            active: route().current('categories.*'),
        },
        {
            label: '取引',
            href: route('transactions.index'),
            active:
                route().current('transactions.*') &&
                !route().current('transactions.category-review.*'),
        },
        {
            label: 'カテゴリ確認',
            href: route('transactions.category-review.index'),
            active: route().current('transactions.category-review.*'),
        },
        {
            label: 'インポート',
            href: route('imports.index'),
            active: route().current('imports.*'),
        },
        {
            label: '分類ルール',
            href: route('classification-rules.index'),
            active: route().current('classification-rules.*'),
        },
    ];

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <div className="flex min-h-screen flex-col lg:flex-row">
                <aside className="border-b border-slate-200 bg-slate-900 text-white lg:min-h-screen lg:w-72 lg:border-b-0 lg:border-r lg:border-slate-800">
                    <div className="flex items-center justify-between px-6 py-5 lg:block">
                        <Link href={route('dashboard')} className="space-y-1">
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                NKKakeist
                            </p>
                            <p className="text-lg font-semibold">
                                個人用資産管理
                            </p>
                        </Link>

                        <button
                            type="button"
                            onClick={() =>
                                setShowingNavigationDropdown((previous) => !previous)
                            }
                            className="inline-flex items-center rounded-md border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800 lg:hidden"
                        >
                            メニュー
                        </button>
                    </div>

                    <div className="hidden px-4 pb-6 lg:block">
                        <div className="rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                            <p className="text-sm font-medium text-slate-100">
                                {user.name}
                            </p>
                            <p className="mt-1 text-sm text-slate-400">
                                {user.email}
                            </p>
                        </div>
                    </div>

                    <nav
                        className={`space-y-1 px-4 pb-6 ${
                            showingNavigationDropdown ? 'block' : 'hidden'
                        } lg:block`}
                    >
                        {navigationItems.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`block rounded-lg px-4 py-3 text-sm font-medium transition ${
                                    item.active
                                        ? 'bg-slate-100 text-slate-900'
                                        : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}

                        <div className="mt-4 border-t border-slate-800 pt-4 lg:hidden">
                            <div className="mb-3 rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                                <p className="text-sm font-medium text-slate-100">
                                    {user.name}
                                </p>
                                <p className="mt-1 text-sm text-slate-400">
                                    {user.email}
                                </p>
                            </div>

                            <Link
                                href={route('profile.edit')}
                                className="block rounded-lg px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
                            >
                                Profile
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="mt-1 block w-full rounded-lg px-4 py-3 text-left text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
                            >
                                Log Out
                            </Link>
                        </div>
                    </nav>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="flex items-center justify-between gap-4 px-6 py-4 lg:px-10">
                            <div className="min-w-0 flex-1">{header}</div>

                            <div className="hidden sm:block">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content align="right" width="48">
                                        <Dropdown.Link href={route('profile.edit')}>
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </header>

                    <main className="px-6 py-8 lg:px-10">{children}</main>
                </div>
            </div>
        </div>
    );
}
