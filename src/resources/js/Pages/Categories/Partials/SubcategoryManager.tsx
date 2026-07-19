import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {
    EditableCategory,
    SubcategoryFormValues,
    SubcategoryItem,
} from '@/types/category';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

type SubcategoryManagerProps = {
    category: EditableCategory;
};

type EditingValues = {
    name: string;
    display_order: string;
    is_active: boolean;
};

export default function SubcategoryManager({
    category,
}: SubcategoryManagerProps) {
    const { t } = useTranslation('categories');
    const [editingSubcategory, setEditingSubcategory] =
        useState<SubcategoryItem | null>(null);

    const createForm = useForm<SubcategoryFormValues>({
        category_id: category.id,
        name: '',
        display_order: '0',
        is_active: true,
    });

    const editForm = useForm<EditingValues>({
        name: '',
        display_order: '0',
        is_active: true,
    });

    const submitCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        createForm.post(route('subcategories.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset('name', 'display_order'),
        });
    };

    const startEditing = (subcategory: SubcategoryItem) => {
        setEditingSubcategory(subcategory);
        editForm.setData({
            name: subcategory.name,
            display_order: String(subcategory.display_order),
            is_active: subcategory.is_active,
        });
        editForm.clearErrors();
    };

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingSubcategory === null) {
            return;
        }

        editForm.put(route('subcategories.update', editingSubcategory.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingSubcategory(null);
                editForm.reset();
            },
        });
    };

    const handleDelete = (subcategory: SubcategoryItem) => {
        if (
            !window.confirm(
                t('subcategories.confirmDelete', { name: subcategory.name }),
            )
        ) {
            return;
        }

        editForm.delete(route('subcategories.destroy', subcategory.id), {
            preserveScroll: true,
        });
    };

    return (
        <section className="space-y-6 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">
                    {t('subcategories.title')}
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                    {t('subcategories.description')}
                </p>
            </div>

            <form onSubmit={submitCreate} className="grid gap-4 md:grid-cols-4">
                <div className="md:col-span-2">
                    <InputLabel
                        htmlFor="subcategory_name"
                        value={t('subcategories.name')}
                    />
                    <TextInput
                        id="subcategory_name"
                        className="mt-1 block w-full"
                        value={createForm.data.name}
                        onChange={(event) =>
                            createForm.setData('name', event.target.value)
                        }
                        required
                    />
                    <InputError
                        className="mt-2"
                        message={createForm.errors.name}
                    />
                </div>

                <div>
                    <InputLabel
                        htmlFor="subcategory_display_order"
                        value={t('subcategories.displayOrder')}
                    />
                    <TextInput
                        id="subcategory_display_order"
                        type="number"
                        min="0"
                        className="mt-1 block w-full"
                        value={createForm.data.display_order}
                        onChange={(event) =>
                            createForm.setData(
                                'display_order',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        className="mt-2"
                        message={createForm.errors.display_order}
                    />
                </div>

                <div className="flex items-end">
                    <PrimaryButton disabled={createForm.processing}>
                        {t('actions.addSubcategory')}
                    </PrimaryButton>
                </div>

                <div className="md:col-span-4 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input
                        id="subcategory_is_active"
                        type="checkbox"
                        checked={createForm.data.is_active}
                        onChange={(event) =>
                            createForm.setData(
                                'is_active',
                                event.target.checked,
                            )
                        }
                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <InputLabel
                        htmlFor="subcategory_is_active"
                        value={t('subcategories.active')}
                    />
                </div>
            </form>

            <div className="space-y-3">
                {category.subcategories.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                        {t('subcategories.empty')}
                    </div>
                ) : (
                    category.subcategories.map((subcategory) => (
                        <div
                            key={subcategory.id}
                            className="rounded-xl border border-slate-200 bg-white p-4"
                        >
                            {editingSubcategory?.id === subcategory.id ? (
                                <form
                                    onSubmit={submitEdit}
                                    className="grid gap-4 md:grid-cols-4"
                                >
                                    <div className="md:col-span-2">
                                        <InputLabel
                                            htmlFor={`edit_subcategory_${subcategory.id}`}
                                            value={t('subcategories.name')}
                                        />
                                        <TextInput
                                            id={`edit_subcategory_${subcategory.id}`}
                                            className="mt-1 block w-full"
                                            value={editForm.data.name}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={editForm.errors.name}
                                        />
                                    </div>

                                    <div>
                                        <InputLabel
                                            htmlFor={`edit_order_${subcategory.id}`}
                                            value={t(
                                                'subcategories.displayOrder',
                                            )}
                                        />
                                        <TextInput
                                            id={`edit_order_${subcategory.id}`}
                                            type="number"
                                            min="0"
                                            className="mt-1 block w-full"
                                            value={editForm.data.display_order}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'display_order',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={
                                                editForm.errors.display_order
                                            }
                                        />
                                    </div>

                                    <div className="flex items-end gap-2">
                                        <PrimaryButton
                                            disabled={editForm.processing}
                                        >
                                            {t('actions.save')}
                                        </PrimaryButton>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditingSubcategory(null)
                                            }
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            {t('actions.cancel')}
                                        </button>
                                    </div>

                                    <div className="md:col-span-4 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <input
                                            id={`edit_active_${subcategory.id}`}
                                            type="checkbox"
                                            checked={editForm.data.is_active}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'is_active',
                                                    event.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <InputLabel
                                            htmlFor={`edit_active_${subcategory.id}`}
                                            value={t('subcategories.active')}
                                        />
                                    </div>
                                </form>
                            ) : (
                                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p className="font-medium text-slate-900">
                                            {subcategory.name}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {t('subcategories.summary', {
                                                order: subcategory.display_order,
                                                status: subcategory.is_active
                                                    ? t('status.active')
                                                    : t('status.inactive'),
                                            })}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                startEditing(subcategory)
                                            }
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            {t('actions.edit')}
                                        </button>
                                        <DangerButton
                                            type="button"
                                            onClick={() =>
                                                handleDelete(subcategory)
                                            }
                                        >
                                            {t('actions.delete')}
                                        </DangerButton>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))
                )}
            </div>
        </section>
    );
}
