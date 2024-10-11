<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Helpers\Array;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ArrayHelperTest extends CIUnitTestCase
{
    public function testGetNestedValue(): void
    {
        $array = [
            'foo' => [
                'bar' => [
                    'baz' => [
                        'a' => 'A',
                        'b' => 'B',
                        'c' => 'C',
                    ],
                    'nullable' => null,
                    'wrong'    => false,
                    'empty'    => '',
                ],
            ],
        ];

        $this->assertSame(['a' => 'A', 'b' => 'B', 'c' => 'C'], ArrayHelper::getNestedValue($array, ['foo', 'bar', 'baz']));
        $this->assertSame(
            ['baz' => ['a' => 'A', 'b' => 'B', 'c' => 'C'], 'nullable' => null, 'wrong' => false, 'empty' => ''],
            ArrayHelper::getNestedValue($array, ['foo', 'bar'])
        );
        $this->assertNull(ArrayHelper::getNestedValue($array, ['foo', 'bar', 'error']));
        $this->assertFalse(ArrayHelper::getNestedValue($array, ['foo', 'bar', 'wrong']));
        $this->assertNull(ArrayHelper::getNestedValue($array, ['foo', 'bar', 'nullable']));
        $this->assertEmpty(ArrayHelper::getNestedValue($array, ['foo', 'bar', 'empty']));
    }

    public function testSetNestedValue(): void
    {
        $array = [
            'foo' => [
                'bar' => [
                    'baz' => [
                        'a' => 'A',
                        'b' => 'B',
                        'c' => 'C',
                    ],
                    'nullable' => null,
                    'wrong'    => false,
                    'empty'    => '',
                ],
            ],
        ];

        $this->assertSame(['foo' => 'foo'], ArrayHelper::setNestedValue(['foo'], 'foo'));
        $this->assertSame(['foo' => ['bar' => 'bar']], ArrayHelper::setNestedValue(['foo', 'bar'], 'bar'));

        $array2 = ArrayHelper::setNestedValue(['foo', 'bar', 'baz'], ['new' => 'new'], $array);
        $array2 = ArrayHelper::setNestedValue(['foo', 'too'], 'too', $array2);

        $this->assertSame('new', $array2['foo']['bar']['baz']['new']);
        $this->assertSame('too', $array2['foo']['too']);

        $this->expectExceptionMessage('Value of "$fromKeys" cannot be an empty array');

        ArrayHelper::setNestedValue([], '');
    }
}
