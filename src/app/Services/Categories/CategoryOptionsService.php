<?php

namespace App\Services\Categories;

class CategoryOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return array_map(
            fn (string $type): array => [
                'value' => $type,
                'label' => trans("categories.types.{$type}"),
            ],
            ['income', 'expense', 'both'],
        );
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
