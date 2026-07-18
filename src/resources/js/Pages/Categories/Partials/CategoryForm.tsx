import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {
    CategoryFormValues,
    CategoryReturnContext,
    CategoryTypeOption,
    EditableCategory,
} from '@/types/category';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type CategoryFormProps = {
    category?: EditableCategory;
    submitLabel: string;
    submitRoute: string;
    method: 'post' | 'put';
    typeOptions: CategoryTypeOption[];
    initialType?: string;
    returnContext?: CategoryReturnContext | null;
    cancelRoute?: string;
};

const defaultValues: CategoryFormValues = {
    name: '',
    type: 'expense',
    color: '',
    icon: '',
    display_order: '0',
    is_active: true,
    return_to: '',
    review_status: '',
    review_type: '',
};

export default function CategoryForm({
    category,
    submitLabel,
    submitRoute,
    method,
    typeOptions,
    initialType,
    returnContext,
    cancelRoute = route('categories.index'),
}: CategoryFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<CategoryFormValues>({
            name: category?.name ?? defaultValues.name,
            type: category?.type ?? initialType ?? defaultValues.type,
            color: category?.color ?? defaultValues.color,
            icon: category?.icon ?? defaultValues.icon,
            display_order: String(
                category?.display_order ?? defaultValues.display_order,
            ),
            is_active: category?.is_active ?? defaultValues.is_active,
            return_to: returnContext?.return_to ?? '',
            review_status: returnContext?.review_status ?? '',
            review_type: returnContext?.review_type ?? '',
        });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
        };

        if (method === 'put') {
            put(submitRoute, options);

            return;
        }

        post(submitRoute, options);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value="カテゴリ名" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        required
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="type" value="種別" />
                    <select
                        id="type"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.type}
                        onChange={(event) => setData('type', event.target.value)}
                    >
                        {typeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.type} />
                </div>

                <div>
                    <InputLabel htmlFor="color" value="色" />
                    <TextInput
                        id="color"
                        className="mt-1 block w-full"
                        placeholder="#2563eb"
                        value={data.color}
                        onChange={(event) => setData('color', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.color} />
                </div>

                <div>
                    <InputLabel htmlFor="icon" value="アイコン名" />
                    <TextInput
                        id="icon"
                        className="mt-1 block w-full"
                        placeholder="shopping-cart"
                        value={data.icon}
                        onChange={(event) => setData('icon', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.icon} />
                </div>

                <div>
                    <InputLabel htmlFor="display_order" value="表示順" />
                    <TextInput
                        id="display_order"
                        type="number"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                    <InputError
                        className="mt-2"
                        message={errors.display_order}
                    />
                </div>

                <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel htmlFor="is_active" value="有効にする" />
                        <p className="text-xs text-slate-500">
                            無効なカテゴリも一覧で状態を確認できます。
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link
                    href={cancelRoute}
                    className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    キャンセル
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
