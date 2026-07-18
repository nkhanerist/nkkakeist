import AppPage from '@/Components/AppPage';
import {
    CategoryReturnContext,
    CategoryTypeOption,
} from '@/types/category';
import CategoryForm from './Partials/CategoryForm';

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
    const cancelRoute = returnContext
        ? route('transactions.category-review.index', {
              status: returnContext.review_status,
              type: returnContext.review_type,
          })
        : route('categories.index');

    return (
        <AppPage
            title="Create Category"
            description={
                returnContext
                    ? 'カテゴリを追加したあと、カテゴリ確認へ戻ります。'
                    : '新しいカテゴリを作成します。'
            }
        >
            <CategoryForm
                method="post"
                submitLabel="作成する"
                submitRoute={route('categories.store')}
                typeOptions={typeOptions}
                initialType={initialType}
                returnContext={returnContext}
                cancelRoute={cancelRoute}
            />
        </AppPage>
    );
}
