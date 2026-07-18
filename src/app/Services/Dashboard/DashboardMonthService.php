<?php

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;

class DashboardMonthService
{
    public function resolve(?string $month): CarbonImmutable
    {
        $normalizedMonth = is_string($month) ? trim($month) : null;

        if ($normalizedMonth === null || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $normalizedMonth)) {
            return CarbonImmutable::now()->startOfMonth();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $normalizedMonth)->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }
}
