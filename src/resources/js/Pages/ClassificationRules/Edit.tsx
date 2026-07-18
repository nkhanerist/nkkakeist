import AppPage from '@/Components/AppPage';
import {
    ClassificationRuleCategoryOption,
    ClassificationRuleListItem,
    ClassificationRuleOption,
    ClassificationRuleSubcategoryOption,
} from '@/types/classification-rule';
import ClassificationRuleForm from './Partials/ClassificationRuleForm';

type EditProps = {
    classificationRule: ClassificationRuleListItem;
    transactionTypeOptions: ClassificationRuleOption[];
    matchFieldOptions: ClassificationRuleOption[];
    matchOperatorOptions: ClassificationRuleOption[];
    categoryOptions: ClassificationRuleCategoryOption[];
    subcategoryOptions: ClassificationRuleSubcategoryOption[];
};

export default function Edit({
    classificationRule,
    ...props
}: EditProps) {
    return (
        <AppPage
            title="分類ルール編集"
            description="自動分類ルールを更新します。"
        >
            <ClassificationRuleForm
                classificationRule={classificationRule}
                method="put"
                submitLabel="更新する"
                submitRoute={route('classification-rules.update', classificationRule.id)}
                {...props}
            />
        </AppPage>
    );
}
