<?php

namespace alcamo\json;

use alcamo\cli\AbstractCli;
use GetOpt\Operand;

class Json2DomCli extends AbstractCli
{
    public const SETTINGS = [
        self::SETTING_STRICT_OPERANDS => true
    ];

    public const OPERANDS = [ 'jsonFilename' => Operand::OPTIONAL ];

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

    public function process($arguments = null)
    {
        parent::process($arguments);

        if (!$this->getOperand('jsonFilename')) {
            $this->showHelp();
            exit;
        }

        $options =
            ($this->getOption('with-json-ptr') ? Json2Dom::JSON_PTR_ATTRS : 0)
            | ($this->getOption('with-xml-id') ? Json2Dom::XML_ID_ATTRS : 0)
            | ($this->getOption('with-always-name-attrs') ? Json2Dom::ALWAYS_NAME_ATTRS : 0);

        $jsonDocument = (new JsonDocumentFactory())->createFromUrl(
            $this->getOperand('jsonFilename')
        );

        $domDocument = $this->createConverter($options)->convert($jsonDocument);

        $domDocument->formatOutput = true;

        echo $domDocument->saveXML();

        exit(0);
    }

    public function createConverter(?int $options = null): Json2Dom
    {
        return new Json2Dom($options);
    }
}
