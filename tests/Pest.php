<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Each surface answers on its own hostname, so a feature test has to say which
 * one it is talking to. These helpers keep that explicit at the call site.
 */
function marketingUrl(string $path = '/'): string
{
    return 'http://'.config('publinza.domains.marketing').$path;
}

function advertiserUrl(string $path = '/'): string
{
    return 'http://'.config('publinza.domains.app').$path;
}

function adminUrl(string $path = ''): string
{
    return 'http://'.config('publinza.domains.marketing').'/'.config('publinza.admin_prefix').$path;
}
