import AppPage from '@/Components/AppPage';
import { CategoryTypeOption, EditableCategory } from '@/types/category';
import CategoryForm from './Partials/CategoryForm';
import SubcategoryManager from './Partials/SubcategoryManager';

type EditProps = {
    category: EditableCategory;
    typeOptions: CategoryTypeOption[];
};

export default function Edit({ category, typeOptions }: EditProps) {
    return (
        <AppPage
            title="Edit Category"
            description="カテゴリ情報とサブカテゴリを更新します。"
        >
            <div className="space-y-8">
                <CategoryForm
                    category={category}
                    method="put"
                    submitLabel="更新する"
                    submitRoute={route('categories.update', category.id)}
                    typeOptions={typeOptions}
                />

                <SubcategoryManager category={category} />
            </div>
        </AppPage>
    );
}
