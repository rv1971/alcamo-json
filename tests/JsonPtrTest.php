<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use PHPUnit\Framework\TestCase;

class JsonPtrTest extends TestCase
{
    /**
     * @dataProvider newFromStringProvider
     */
    public function testNewFromString(
        $string,
        $expectedSegments,
        $expectedIsRoot
    ): void {
        $jsonPtr = JsonPtr::newFromString($string);

        $this->assertSame(
            count($expectedSegments),
            count($jsonPtr)
        );

        $this->assertSame($expectedSegments, $jsonPtr->toArray());

        foreach ($jsonPtr as $i => $segment) {
            $this->assertSame($segment, $expectedSegments[$i]);
        }

        $this->assertSame($string, (string)$jsonPtr);

        $this->assertSame($expectedIsRoot, $jsonPtr->isRoot());
    }

    public function newFromStringProvider(): array
    {
        return [
            [ '/', [], true ],
            [
                '/foo~0~01bar/~1baz~1qux~0~1',
                [ 'foo~~1bar', '/baz/qux~/' ],
                false
            ]
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
        $jsonPtr = JsonPtr::newFromString($string)->appendSegment($segment);

        $this->assertSame($expectedString, (string)$jsonPtr);
    }

    public function appendSegmentProvider(): array
    {
        return [
            [ '/', 'foo', '/foo' ],
            [ '/~0~1foo', '~/bar/~', '/~0~1foo/~0~1bar~1~0' ]
        ];
    }

    /**
     * @dataProvider appendSegmentsProvider
     */
    public function testAppendSegments(
        $string,
        $segments,
        $expectedString
    ): void {
        $jsonPtr = JsonPtr::newFromString($string)
            ->appendSegments(JsonPtrSegments::newFromString($segments));

        $this->assertSame($expectedString, (string)$jsonPtr);
    }

    public function appendSegmentsProvider(): array
    {
        return [
            [ '/', 'foo/bar', '/foo/bar' ],
            [ '/~0~1foo', 'baz/~0qux~1/quux', '/~0~1foo/baz/~0qux~1/quux' ]
        ];
    }
}
