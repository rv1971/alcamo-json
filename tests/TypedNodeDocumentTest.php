<?php

namespace alcamo\json;

use alcamo\exception\{InvalidEnumerator, ProgramFlowException};
use alcamo\uri\Uri;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'FooDocument.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Foo2Document.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Foo3Document.php';

class TypedNodeDocumentTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testBasics()
    {
        $doc = (new FooDocumentFactory())->createFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        $this->assertInstanceOf(FooNode::class, $doc->getRoot()->foo);
        $this->assertInstanceOf(SlashNode::class, $doc->getRoot()->foo->{'/'});
        $this->assertInstanceOf(TildeTildeNode::class, $doc->getRoot()->foo->{'~~'});

        $this->assertInstanceOf(BarNode::class, $doc->getRoot()->bar);
        $this->assertInstanceOf(BazNode::class, $doc->getRoot()->bar->baz);
        $this->assertInstanceOf(QuxNode::class, $doc->getRoot()->bar->baz->qux[5]);
        $this->assertInstanceOf(QuuxNode::class, $doc->getRoot()->bar->baz->qux[6][0][2]);
        $this->assertInstanceOf(
            QuuxNode::class,
            $doc->getRoot()->bar->baz->qux[6][0][2]->QUUX
        );
        $this->assertInstanceOf(Foo2Node::class, $doc->getRoot()->bar->baz->qux[6][1][1]);

        $this->assertInstanceOf(OtherNode::class, $doc->getRoot()->baz);
    }

    public function testNotFoundException()
    {
        $this->expectException(InvalidEnumerator::class);
        $this->expectExceptionMessage(
            'Invalid value "baz", expected one of ["foo", "bar"]'
        );

        $doc = (new FooDocumentFactory())->createFromJsonText(
            file_get_contents(self::FOO_FILENAME),
            null,
            Foo2Document::class
        );
    }

    public function testNoMapException()
    {
        $this->expectException(ProgramFlowException::class);
        $this->expectExceptionMessage(
            'No CLASS_MAP in "alcamo\json\Foo3Node" at URI "http://www.example.com"'
        );

        $doc = (new FooDocumentFactory())->createFromJsonText(
            file_get_contents(self::FOO_FILENAME),
            new Uri('http://www.example.com'),
            Foo3Document::class
        );
    }
}
