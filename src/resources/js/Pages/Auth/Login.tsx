import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

export default function Login({
    status,
    canResetPassword,
    canUseDevelopmentLogin,
}: {
    status?: string;
    canResetPassword: boolean;
    canUseDevelopmentLogin: boolean;
}) {
    const { t } = useTranslation('auth');
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const {
        post: postDevelopmentLogin,
        processing: developmentLoginProcessing,
    } = useForm({});

    const submitDevelopmentLogin: FormEventHandler = (e) => {
        e.preventDefault();
        postDevelopmentLogin(route('development-login'));
    };

    return (
        <GuestLayout>
            <Head title={t('login.title')} />

            <div className="mb-6">
                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                    {t('login.eyebrow')}
                </p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
                    {t('login.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-slate-500">
                    {t('login.description')}
                </p>
            </div>

            {status && (
                <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel
                        htmlFor="email"
                        value={t('common.email')}
                        className="text-slate-700"
                    />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 block h-12 w-full rounded-xl border-slate-300 px-4 shadow-none focus:border-emerald-600 focus:ring-emerald-600"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password"
                        value={t('common.password')}
                        className="text-slate-700"
                    />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 block h-12 w-full rounded-xl border-slate-300 px-4 shadow-none focus:border-emerald-600 focus:ring-emerald-600"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between gap-4">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-slate-600">
                            {t('login.remember')}
                        </span>
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm font-medium text-emerald-700 transition hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            {t('login.forgotPassword')}
                        </Link>
                    )}
                </div>

                <PrimaryButton
                    className="h-12 w-full justify-center rounded-xl bg-slate-950 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800 focus:ring-emerald-500 active:bg-slate-950"
                    disabled={processing}
                >
                    <span>{t('login.submit')}</span>
                    <svg
                        viewBox="0 0 20 20"
                        fill="none"
                        className="ms-2 h-4 w-4"
                        aria-hidden="true"
                    >
                        <path
                            d="M4 10h12m-4-4 4 4-4 4"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </PrimaryButton>
            </form>

            {canUseDevelopmentLogin && (
                <>
                    <div className="my-5 flex items-center gap-3" aria-hidden="true">
                        <div className="h-px flex-1 bg-slate-200" />
                        <span className="text-xs font-medium text-slate-400">
                            {t('login.developmentSection')}
                        </span>
                        <div className="h-px flex-1 bg-slate-200" />
                    </div>

                    <form onSubmit={submitDevelopmentLogin}>
                        <PrimaryButton
                            className="h-12 w-full justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-sm normal-case tracking-normal text-emerald-800 hover:bg-emerald-100 focus:bg-emerald-100 focus:ring-emerald-500 active:bg-emerald-100"
                            disabled={developmentLoginProcessing}
                        >
                            {t('login.developmentSubmit')}
                        </PrimaryButton>
                        <p className="mt-2 text-center text-xs leading-5 text-slate-500">
                            {t('login.developmentDescription')}
                        </p>
                    </form>
                </>
            )}
        </GuestLayout>
    );
}
