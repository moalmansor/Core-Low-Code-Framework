<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\EngineTruncation;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Integration');
// Engine tests run real DDL (implicit commits on MySQL): truncate instead of
// wrapping each test in a transaction, and drop generated record tables.
pest()->extend(TestCase::class)->use(EngineTruncation::class)
    ->beforeEach(fn () => $this->dropRecordTables())
    ->afterEach(fn () => $this->dropRecordTables())
    ->in('Engine');
