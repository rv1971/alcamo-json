<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use PHPUnit\Framework\TestCase;

class Json2DomTest extends TestCase
{
    public function setUp(): void
    {
        chdir(__DIR__);
    }

    /**
     * @dataProvider convertProvider
     */
    public function testConvert($jsonFileName, $xmlFileName): void
    {
        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            'json2dom' . DIRECTORY_SEPARATOR . $jsonFileName
        );

        $domDocument = new \DOMDocument();

        $domDocument->load(
            'json2dom' . DIRECTORY_SEPARATOR . $xmlFileName,
            LIBXML_NOBLANKS
        );

        $domDocument->formatOutput = true;

        $domDocument2 =
            (new Json2Dom(Json2Dom::JSON_PTR_ATTRS | Json2Dom::XML_ID_ATTRS))
            ->convert($jsonDocument);

        $domDocument2->formatOutput = true;

        $this->assertSame(
            $domDocument->saveXML(),
            $domDocument2->saveXML()
        );
    }

    public function convertProvider(): array
    {
        return [
            [ 'true.json', 'true.xml' ],
            [ 'attributes.json', 'attributes.xml' ],
            [ 'objects.json', 'objects.xml' ],
            [ 'arrays.json', 'arrays.xml' ]
        ];
    }

    public function testConvertNoBaseUrl(): void
    {
        $jsonDocument = (new JsonDocumentFactory())->createFromJsonText(
            file_get_contents(
                'json2dom' . DIRECTORY_SEPARATOR . 'attributes.json'
            )
        );

        $domDocument = new \DOMDocument();

        $domDocument->load(
            'json2dom' . DIRECTORY_SEPARATOR . 'attributes-no-base.xml',
            LIBXML_NOBLANKS
        );

        $domDocument->formatOutput = true;

        $domDocument2 =
            (new Json2Dom(Json2Dom::JSON_PTR_ATTRS | Json2Dom::XML_ID_ATTRS))
            ->convert($jsonDocument);

        $domDocument2->formatOutput = true;

        $this->assertSame(
            $domDocument->saveXML(),
            $domDocument2->saveXML()
        );
    }

    public function testConvertNoJsonPtr(): void
    {
        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays.json'
        );

        $domDocument = new \DOMDocument();

        $domDocument->load(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays-no-json-ptr.xml',
            LIBXML_NOBLANKS
        );

        $domDocument->formatOutput = true;

        $domDocument2 =
            (new Json2Dom(Json2Dom::XML_ID_ATTRS))->convert($jsonDocument);

        $domDocument2->formatOutput = true;

        $this->assertSame(
            $domDocument->saveXML(),
            $domDocument2->saveXML()
        );
    }

    public function testConvertNoXmlId(): void
    {
        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays.json'
        );

        $domDocument = new \DOMDocument();

        $domDocument->load(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays-no-xml-id.xml',
            LIBXML_NOBLANKS
        );

        $domDocument->formatOutput = true;

        $domDocument2 =
            (new Json2Dom(Json2Dom::JSON_PTR_ATTRS))->convert($jsonDocument);

        $domDocument2->formatOutput = true;

        $this->assertSame(
            $domDocument->saveXML(),
            $domDocument2->saveXML()
        );
    }

    public function testConvertAlwaysNames(): void
    {
        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays.json'
        );

        $domDocument = new \DOMDocument();

        $domDocument->load(
            'json2dom' . DIRECTORY_SEPARATOR . 'arrays-always-name.xml',
            LIBXML_NOBLANKS
        );

        $domDocument->formatOutput = true;

        $domDocument2 =
            (new Json2Dom(Json2Dom::ALWAYS_NAME_ATTRS))->convert($jsonDocument);

        $domDocument2->formatOutput = true;

        $this->assertSame(
            $domDocument->saveXML(),
            $domDocument2->saveXML()
        );
    }
}
