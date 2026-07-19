import AppPage from '@/Components/AppPage';
import {
    ClassificationRuleCategoryOption,
    ClassificationRuleListItem,
    ClassificationRuleOption,
    ClassificationRuleSubcategoryOption,
} from '@/types/classification-rule';
import ClassificationRuleForm from './Partials/ClassificationRuleForm';
import { useTranslation } from 'react-i18next';

type EditProps = {
    classificationRule: ClassificationRuleListItem;
    transactionTypeOptions: ClassificationRuleOption[];
    matchFieldOptions: ClassificationRuleOption[];
    matchOperatorOptions: ClassificationRuleOption[];
    categoryOptions: ClassificationRuleCategoryOption[];
    subcategoryOptions: ClassificationRuleSubcategoryOption[];
};

export default function Edit({ classificationRule, ...props }: EditProps) {
    const { t } = useTranslation('classificationRules');

    return (
        <AppPage title={t('edit.title')} description={t('edit.description')}>
            <ClassificationRuleForm
                classificationRule={classificationRule}
                method="put"
                submitLabel={t('edit.submit')}
                submitRoute={route(
                    'classification-rules.update',
                    classificationRule.id,
                )}
                {...props}
            />
        </AppPage>
    );
}
