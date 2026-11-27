<?php

namespace Dialog\AskDialog\Tests\Unit\Service\OrderLookup;

use Dialog\AskDialog\Service\OrderLookup\OrderOwnership;
use PHPUnit\Framework\TestCase;

class OrderOwnershipTest extends TestCase
{
    public function testMatchesTheOrderEmailWhateverItsCaseAndSpaces()
    {
        $this->assertTrue(OrderOwnership::matches(['Jane.Doe@Example.com'], '  jane.doe@example.COM '));
    }

    public function testRefusesAnotherEmail()
    {
        $this->assertFalse(OrderOwnership::matches(['jane@example.com'], 'john@example.com'));
    }

    public function testRefusesAnUnknownReference()
    {
        $this->assertFalse(OrderOwnership::matches([], 'jane@example.com'));
    }

    public function testRefusesTheStandInOfAnUnknownReference()
    {
        $this->assertFalse(OrderOwnership::matches([], 'no-order@askdialog.invalid'));
    }

    public function testNeedsTheEmailOfEveryPartOfASplitOrder()
    {
        $this->assertTrue(OrderOwnership::matches(['jane@example.com', 'jane@example.com'], 'jane@example.com'));
        $this->assertFalse(OrderOwnership::matches(['jane@example.com', 'john@example.com'], 'jane@example.com'));
    }

    public function testRefusesAnOrderWithoutCustomer()
    {
        $this->assertFalse(OrderOwnership::matches([null], ''));
    }
}
