<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase; // اضافه شدن این خط

abstract class TestCase extends BaseTestCase
{
    // استفاده از این تِرِیت برای بازسازی دیتابیس در محیط تست
    use RefreshDatabase; 
}