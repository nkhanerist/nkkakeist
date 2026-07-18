<?php

namespace App\Services\Categories;

class CategoryOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return [
            ['value' => 'income', 'label' => '収入'],
            ['value' => 'expense', 'label' => '支出'],
            ['value' => 'both', 'label' => '両方'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        $labels = [];

        foreach ($this->typeOptions() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $labels;
    }
}
