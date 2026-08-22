<?php

namespace App\Modules\Manufacturing\DTOs;

readonly class WorkCenterDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public float $capacity_hours_per_day,
        public float $efficiency_percentage,
        public float $cost_per_hour,
        public int $status,
        public string $created_by
    ) {}
}