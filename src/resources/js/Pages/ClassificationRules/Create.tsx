import AppPage from '@/Components/AppPage';
import {
    ClassificationRuleCategoryOption,
    ClassificationRuleOption,
    ClassificationRuleSubcategoryOption,
} from '@/types/classification-rule';
import ClassificationRuleForm from './Partials/ClassificationRuleForm';

type CreateProps = {
    transactionTypeOptions: ClassificationRuleOption[];
    matchFieldOptions: ClassificationRuleOption[];
    matchOperatorOptions: ClassificationRuleOption[];
    categoryOptions: ClassificationRuleCategoryOption[];
    subcategoryOptions: ClassificationRuleSubcategoryOption[];
};

export default function Create(props: CreateProps) {
    return (
        <AppPage
            title="分類ルール作成"
            description="インポートプレビューの自動分類ルールを追加します。"
        >
            <ClassificationRuleForm
                method="post"
                submitLabel="作成する"
                submitRoute={route('classification-rules.store')}
                {...props}
            />
        </AppPage>
    );
}
