export type CategoryTypeOption = {
    value: string;
    label: string;
};

export type SubcategoryItem = {
    id: number;
    name: string;
    display_order: number;
    is_active: boolean;
};

export type CategoryListItem = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    color: string | null;
    icon: string | null;
    display_order: number;
    is_active: boolean;
    subcategories: SubcategoryItem[];
};

export type EditableCategory = {
    id: number;
    name: string;
    type: string;
    color: string | null;
    icon: string | null;
    display_order: number;
    is_active: boolean;
    subcategories: SubcategoryItem[];
};

export type CategoryFormValues = {
    name: string;
    type: string;
    color: string;
    icon: string;
    display_order: string;
    is_active: boolean;
    return_to: string;
    review_status: string;
    review_type: string;
};

export type CategoryReturnContext = {
    return_to: 'category-review';
    review_status: 'high' | 'manual' | 'all';
    review_type: 'expense' | 'income' | 'all';
};

export type SubcategoryFormValues = {
    category_id: number;
    name: string;
    display_order: string;
    is_active: boolean;
};
