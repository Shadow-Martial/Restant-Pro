<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BasicUnitTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test basic PHP functionality.
     */
    public function test_array_operations(): void
    {
        $array = [1, 2, 3];
        $this->assertCount(3, $array);
        $this->assertContains(2, $array);
    }

    /**
     * Test string operations.
     */
    public function test_string_operations(): void
    {
        $string = 'Restant SaaS Platform';
        $this->assertStringContainsString('SaaS', $string);
        $this->assertEquals(20, strlen($string));
    }
}