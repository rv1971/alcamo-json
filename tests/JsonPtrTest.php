<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use PHPUnit\Framework\TestCase;

class JsonPtrTest extends TestCase
{
    /**
     * @dataProvider newFromStringProvider
     */
    public function testNewFromString($string, $expectedSegments): void
    {
        $jsonPtr = JsonPtr::newFromString($string);

        $this->assertSame(
            count($expectedSegments),
            count($jsonPtr)
        );

        foreach ($jsonPtr as $i => $segment) {
            $this->assertSame($segment, $expectedSegments[$i]);
        }

        $this->assertSame($string, (string)$jsonPtr);
    }

    public function newFromStringProvider(): array
    {
        return [
            [ '/', [] ],
            [ '/foo~0~01bar/~1baz~1qux~0~1', [ 'foo~~1bar', '/baz/qux~/' ] ]
        ];
    }

    public function testNewFromStringException(): void
    {
        $this->expectException(SyntaxError::class);
        $this-> expectExceptionMessage(
            'Syntax error in "foo" at offset 0 ("foo"); '
            . 'JSON pointer must begin with slash'
        );

        JsonPtr::newFromString('foo');
    }

    /**
     * @dataProvider appendSegmentProvider
     */
    public function testAppendSegment($string, $segment, $expectedString): void
    {
        $jsonPtr = JsonPtr::newFromString($string);

        $jsonPtr->appendSegment($segment);

        $this->assertSame($expectedString, (string)$jsonPtr);
    }

    public function appendSegmentProvider(): array
    {
        return [
            [ '/', 'foo', '/foo' ],
            [ '/~0~1foo', '~/bar/~', '/~0~1foo/~0~1bar~1~0' ]
        ];
    }
}
