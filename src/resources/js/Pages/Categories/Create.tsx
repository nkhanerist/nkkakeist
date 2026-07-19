import AppPage from '@/Components/AppPage';
import { CategoryReturnContext, CategoryTypeOption } from '@/types/category';
import CategoryForm from './Partials/CategoryForm';
import { useTranslation } from 'react-i18next';

type CreateProps = {
    typeOptions: CategoryTypeOption[];
    initialType: string;
    returnContext: CategoryReturnContext | null;
};

export default function Create({
    typeOptions,
    initialType,
    returnContext,
}: CreateProps) {
    const { t } = useTranslation('categories');
    const cancelRoute = returnContext
        ? route('transactions.category-review.index', {
              status: returnContext.review_status,
              type: returnContext.review_type,
          })
        : route('categories.index');

    return (
        <AppPage
            title={t('create.title')}
            description={
                returnContext
                    ? t('create.reviewDescription')
                    : t('create.description')
            }
        >
            <CategoryForm
                method="post"
                submitLabel={t('create.submit')}
                submitRoute={route('categories.store')}
                typeOptions={typeOptions}
                initialType={initialType}
                returnContext={returnContext}
                cancelRoute={cancelRoute}
            />
        </AppPage>
    );
}
