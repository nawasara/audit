<?php

declare(strict_types=1);

namespace Nawasara\Audit\Tests;

use Nawasara\Audit\Support\RetentionPolicy;
use PHPUnit\Framework\TestCase;

/**
 * A bad retention value must stop the prune, never widen it.
 */
class RetentionPolicyTest extends TestCase
{
    public function test_valid_values_are_accepted(): void
    {
        $this->assertSame(90, RetentionPolicy::days(90));
        $this->assertSame(90, RetentionPolicy::days('90'));
        $this->assertSame(365, RetentionPolicy::days(' 365 '));
        $this->assertSame(30, RetentionPolicy::days(30));
    }

    /** "9" typed instead of "90" would delete almost everything. */
    public function test_values_below_the_minimum_are_refused(): void
    {
        $this->assertNull(RetentionPolicy::days(0));
        $this->assertNull(RetentionPolicy::days(9));
        $this->assertNull(RetentionPolicy::days('29'));
    }

    public function test_malformed_values_are_refused(): void
    {
        $this->assertNull(RetentionPolicy::days(''));
        $this->assertNull(RetentionPolicy::days(null));
        $this->assertNull(RetentionPolicy::days('-90'));
        $this->assertNull(RetentionPolicy::days('90 days'));
        $this->assertNull(RetentionPolicy::days(90.5));
        $this->assertNull(RetentionPolicy::days(false));
    }
}
