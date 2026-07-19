import AppPage from '@/Components/AppPage';
import { CategoryTypeOption, EditableCategory } from '@/types/category';
import CategoryForm from './Partials/CategoryForm';
import SubcategoryManager from './Partials/SubcategoryManager';
import { useTranslation } from 'react-i18next';

type EditProps = {
    category: EditableCategory;
    typeOptions: CategoryTypeOption[];
};

export default function Edit({ category, typeOptions }: EditProps) {
    const { t } = useTranslation('categories');

    return (
        <AppPage title={t('edit.title')} description={t('edit.description')}>
            <div className="space-y-8">
                <CategoryForm
                    category={category}
                    method="put"
                    submitLabel={t('edit.submit')}
                    submitRoute={route('categories.update', category.id)}
                    typeOptions={typeOptions}
                />

                <SubcategoryManager category={category} />
            </div>
        </AppPage>
    );
}
