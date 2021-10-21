<?php

namespace alcamo\json;

use alcamo\exception\{InvalidEnumerator, ProgramFlowException};
use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'FooDocument.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Foo2Document.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Foo3Document.php';

class TypedNodeDocumentTraitTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testBasics()
    {
        $doc = new FooDocument(
            json_decode(file_get_contents(self::FOO_FILENAME))
        );

        $this->assertInstanceOf(FooNode::class, $doc->foo);
        $this->assertInstanceOf(SlashNode::class, $doc->foo->{'/'});
        $this->assertInstanceOf(TildeTildeNode::class, $doc->foo->{'~~'});

        $this->assertInstanceOf(BarNode::class, $doc->bar);
        $this->assertInstanceOf(BazNode::class, $doc->bar->baz);
        $this->assertInstanceOf(QuxNode::class, $doc->bar->baz->qux[5]);
        $this->assertInstanceOf(QuuxNode::class, $doc->bar->baz->qux[6][0][2]);
        $this->assertInstanceOf(
            QuuxNode::class,
            $doc->bar->baz->qux[6][0][2]->QUUX
        );
        $this->assertInstanceOf(Foo2Node::class, $doc->bar->baz->qux[6][1][1]);

        $this->assertInstanceOf(OtherNode::class, $doc->baz);
    }

    public function testNotFoundException()
    {
        $this->expectException(InvalidEnumerator::class);
        $this->expectExceptionMessage(
            'Invalid value "baz", expected one of ["foo", "bar"] at URI "#/baz"'
        );

        $doc = new Foo2Document(
            json_decode(file_get_contents(self::FOO_FILENAME))
        );
    }

    public function testNoMapException()
    {
        $this->expectException(ProgramFlowException::class);
        $this->expectExceptionMessage(
            'No CLASS_MAP in "alcamo\json\Foo3Node" at URI "#/"'
        );

        $doc = new Foo3Document(
            json_decode(file_get_contents(self::FOO_FILENAME))
        );
    }
}
