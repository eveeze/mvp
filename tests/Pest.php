<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Bootstrap
|--------------------------------------------------------------------------
|
| - Semua test di Feature & Unit pakai Tests\TestCase (Laravel)
| - Semua Feature test auto pakai RefreshDatabase (DB reset tiap test)
|
*/

uses(TestCase::class)->in('Feature', 'Unit');
uses(RefreshDatabase::class)->in('Feature');
