import AppPage from '@/Components/AppPage';
import {
    ClassificationRuleCategoryOption,
    ClassificationRuleOption,
    ClassificationRuleSubcategoryOption,
} from '@/types/classification-rule';
import ClassificationRuleForm from './Partials/ClassificationRuleForm';
import { useTranslation } from 'react-i18next';

type CreateProps = {
    transactionTypeOptions: ClassificationRuleOption[];
    matchFieldOptions: ClassificationRuleOption[];
    matchOperatorOptions: ClassificationRuleOption[];
    categoryOptions: ClassificationRuleCategoryOption[];
    subcategoryOptions: ClassificationRuleSubcategoryOption[];
};

export default function Create(props: CreateProps) {
    const { t } = useTranslation('classificationRules');

    return (
        <AppPage
            title={t('create.title')}
            description={t('create.description')}
        >
            <ClassificationRuleForm
                method="post"
                submitLabel={t('create.submit')}
                submitRoute={route('classification-rules.store')}
                {...props}
            />
        </AppPage>
    );
}
