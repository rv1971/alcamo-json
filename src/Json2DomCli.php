<?php

namespace alcamo\json;

use alcamo\cli\AbstractCli;
use GetOpt\Operand;

class Json2DomCli extends AbstractCli
{
    public const OPERANDS = [ 'jsonFilename' => Operand::REQUIRED ];

    public const OPTIONS = parent::OPTIONS + [
        'with-always-name-attrs' => [
            null,
            self::NO_ARGUMENT,
            'With name attributes in all elements'
        ],
        'with-json-ptr' => [
            null,
            self::NO_ARGUMENT,
            'With jsonPtr attributes'
        ],
        'with-xml-id' => [
            null,
            self::NO_ARGUMENT,
            'With xml:id attributes'
        ]
    ];

    public const OPTIONS_MAP = [
        'with-always-name-attrs' => Json2Dom::ALWAYS_NAME_ATTRS,
        'with-json-ptr'          => Json2Dom::JSON_PTR_ATTRS,
        'with-xml-id'            => Json2Dom::XML_ID_ATTRS
    ];

    public function innerRun($arguments = null): int
    {
        $json2DomOptions = 0;

        foreach (static::OPTIONS_MAP as $cliOption => $json2DomOption) {
            if ($this->getOption($cliOption)) {
                $json2DomOptions |= $json2DomOption;
            }
        }

        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            $this->getOperand('jsonFilename')
        );

        $domDocument = $this->createConverter($json2DomOptions)
            ->convert($jsonDocument);

        $domDocument->formatOutput = true;

        echo $domDocument->saveXML();

        return 0;
    }

    public function createConverter(?int $json2DomOptions = null): Json2Dom
    {
        return new Json2Dom($json2DomOptions);
    }
}
