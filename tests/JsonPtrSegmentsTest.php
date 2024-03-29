<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use PHPUnit\Framework\TestCase;

class JsonPtrSegmentsTest extends TestCase
{
    /**
     * @dataProvider newFromStringProvider
     */
    public function testNewFromString(
        $string,
        $expectedSegments,
        $expectedParent,
        $expectedIsEmpty
    ): void {
        $jsonPtrSegments = JsonPtrSegments::newFromString($string);

        $this->assertSame(
            count($expectedSegments),
            count($jsonPtrSegments)
        );

        foreach ($jsonPtrSegments as $i => $segment) {
            $this->assertSame($segment, $expectedSegments[$i]);
        }

        $this->assertSame($expectedSegments, $jsonPtrSegments->toArray());

        $this->assertSame($string, (string)$jsonPtrSegments);

        if (isset($expectedParent)) {
            $this->assertEquals(
                $expectedParent,
                (string)$jsonPtrSegments->getParent()
            );
        } else {
            $this->assertNull($jsonPtrSegments->getParent());
        }

        $this->assertSame($expectedIsEmpty, $jsonPtrSegments->isEmpty());
    }

    public function newFromStringProvider(): array
    {
        return [
            [ '', [], null, true ],
            [
                'foo~0~01bar/~1baz~1qux~0~1',
                [ 'foo~~1bar', '/baz/qux~/' ],
                'foo~0~01bar',
                false
            ]
        ];
    }

    public function testNewFromStringException(): void
    {
        $this->expectException(SyntaxError::class);
        $this-> expectExceptionMessage(
            'Syntax error in "/foo" at offset 0 ("/foo"); '
            . 'sequence of JSON pointer segments must not start with slash'
        );

        JsonPtrSegments::newFromString('/foo');
    }

    /**
     * @dataProvider appendSegmentProvider
     */
    public function testAppendSegment($string, $segment, $expectedString): void
    {
        $jsonPtrSegments =
            JsonPtrSegments::newFromString($string)->appendSegment($segment);

        $this->assertSame($expectedString, (string)$jsonPtrSegments);
    }

    public function appendSegmentProvider(): array
    {
        return [
            [ '', 'foo', 'foo' ],
            [ '~0~1foo', '~/bar/~', '~0~1foo/~0~1bar~1~0' ]
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
        $jsonPtrSegments = JsonPtrSegments::newFromString($string)
            ->appendSegments(JsonPtrSegments::newFromString($segments));

        $this->assertSame($expectedString, (string)$jsonPtrSegments);
    }

    public function appendSegmentsProvider(): array
    {
        return [
            [ '', 'foo/bar', 'foo/bar' ],
            [ '~0~1foo', 'baz/~0qux~1/quux', '~0~1foo/baz/~0qux~1/quux' ]
        ];
    }
}
