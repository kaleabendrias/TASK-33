<?php

namespace UnitTests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends \Tests\TestCase
{
    use RefreshDatabase;
}
