<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief Convert a JSON structure to a generc DOM tree
 *
 * - Properties whose value are JSON objects are converted to DOM elements
 *   whose namespace is @ref OBJECT_NS and whose local name is the name of the
 *   JSON property converted by $jsonProp2localName().
 * - In properties whose value are arrays:
 *   - Sub-objects are converted to <s:item> elements where s resolves to the
 *     @ref STRUCTURE_NS namespace.
 *   - Primitive values are converted to attributes named _1, _2 etc.
 *   - Sub-arrays are converted accordingly.
 * - All other properties are converted to attributes without namespace whose
 *   local name is the name of the JSON property converted by
 *   $jsonProp2localName().
 *
 * @warning The result does not distinguish between empty arrays and empyt
 * objects.
 *
 * Among others, this can be used to generate an HTML representation of a JSON
 * object by converting it to XML and applying an XSLT stylesheet.
 */
class Json2Dom
{
    public const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /// Namespace for DOM elements that represent a JSON object
    public const OBJECT_NS = 'tag:rv1971@web.de,2021:alcamo-json:j2d/object';

    /// Namespace for DOM elements that needed for structures, e.g. arrays
    public const STRUCTURE_NS =
        'tag:rv1971@web.de,2021:alcamo-json:j2d/structure';

    /// Local name of the document element
    public const DOCUMENT_LOCAL_NAME = 'document';

    public const JSON_PTR_ATTRS = 1; ///< Flag value to add jsonPtr attributes
    public const XML_ID_ATTRS = 2;   ///< Flag value to add xml:id attributes

    private $flags_;

    public function __construct(int $flags = null)
    {
        $this->flags_ = $flags;
    }

    public function convert(JsonDocumentInterface $jsonDocument): \DOMDocument
    {
        $domDocument = new \DOMDocument();

        $this->appendJsonNode(
            $domDocument,
            $jsonDocument,
            static::STRUCTURE_NS,
            's:' . static::DOCUMENT_LOCAL_NAME
        );

        $domDocument->documentElement->setAttribute('xmlns', static::OBJECT_NS);

        if ($jsonDocument->getBaseUri() !== null) {
            $domDocument->documentElement->setAttributeNS(
                self::XML_NS,
                'xml:base',
                $jsonDocument->getBaseUri()
            );
        }

        return $domDocument;
    }

    public function appendJsonNode(
        \DOMNode $domNode,
        JsonNode $jsonNode,
        string $nsName,
        string $qName
    ): void {
        $child = $nsName == static::OBJECT_NS
            ? ($domNode->ownerDocument ?? $domNode)->createElement($qName)
            : ($domNode->ownerDocument ?? $domNode)
            ->createElementNS($nsName, $qName);

        if ($jsonNode->getJsonPtr() != '/') {
            if ($this->flags_ & self::JSON_PTR_ATTRS) {
                $child->setAttribute('jsonPtr', $jsonNode->getJsonPtr());
            }

            if ($this->flags_ & self::XML_ID_ATTRS) {
                $child->setAttributeNS(
                    self::XML_NS,
                    'xml:id',
                    $this->jsonPtr2XmlId($jsonNode->getJsonPtr())
                );
            }
        }

        $domNode->appendChild($child);

        foreach ($jsonNode as $prop => $value) {
            $localName = $this->jsonProp2localName($prop);

            switch (true) {
                case is_object($value):
                    $this->appendJsonNode(
                        $child,
                        $value,
                        static::OBJECT_NS,
                        $localName
                    );
                    break;

                case is_array($value):
                    $this->appendArray(
                        $child,
                        $value,
                        static::OBJECT_NS,
                        $localName,
                        JsonNode::composeJsonPtr($jsonNode->getJsonPtr(), $prop)
                    );
                    break;

                default:
                    $this->appendValue(
                        $child,
                        $value,
                        static::OBJECT_NS,
                        $localName,
                        JsonNode::composeJsonPtr($jsonNode->getJsonPtr(), $prop)
                    );
            }
        }
    }

    public function appendArray(
        \DOMNode $domNode,
        array $jsonArray,
        string $nsName,
        string $qName,
        string $jsonPtr
    ): void {
        $child = $domNode->ownerDocument->createElementNS($nsName, $qName);

        $domNode->appendChild($child);

        if ($this->flags_ & self::JSON_PTR_ATTRS) {
            $child->setAttribute('jsonPtr', $jsonPtr);
        }

        if ($this->flags_ & self::XML_ID_ATTRS) {
            $child->setAttributeNS(
                self::XML_NS,
                'xml:id',
                $this->jsonPtr2XmlId($jsonPtr)
            );
        }

        foreach ($jsonArray as $pos => $item) {
            switch (true) {
                case is_object($item):
                    $this->appendJsonNode(
                        $child,
                        $item,
                        static::STRUCTURE_NS,
                        's:item'
                    );
                    break;

                case is_array($item):
                    $this->appendArray(
                        $child,
                        $item,
                        static::STRUCTURE_NS,
                        's:item',
                        "$jsonPtr/$pos"
                    );
                    break;

                default:
                    $this->appendValue(
                        $child,
                        $item,
                        static::STRUCTURE_NS,
                        's:item',
                        "$jsonPtr/$pos"
                    );
            }
        }
    }

    public function appendValue(
        \DOMNode $domNode,
        $value,
        string $nsName,
        string $localName,
        string $jsonPtr
    ): void {
        $child = $domNode->ownerDocument->createElement(
            $localName,
            is_bool($value) ? ['false', 'true'][(int)$value] : $value
        );

        $domNode->appendChild($child);

        if ($this->flags_ & self::JSON_PTR_ATTRS) {
            $child->setAttribute('jsonPtr', $jsonPtr);
        }

        if ($this->flags_ & self::XML_ID_ATTRS) {
            $child->setAttributeNS(
                self::XML_NS,
                'xml:id',
                $this->jsonPtr2XmlId($jsonPtr)
            );
        }
    }

    /**
     * @brief Convert a JSON property name to an XML local name
     *
     * @warning Distinct JSON property names may be converted to the same XML
     * local name if they only differ in characters that are not allowed in
     * the latter.
     *
     * @warning No special handling is implemented for non-ASCII
     * characters. Property names containing non-ASCII characters that are not
     * allowed in an XML [Name](https://www.w3.org/TR/REC-xml/#NT-Name) or its
     * first character will result in invalid DOM documents.
     */
    public function jsonProp2localName(string $jsonProp): string
    {
        /**- Convert a space to an underscore, a slash to a dot and any other
         * special ASCII character to a hyphen. */
        $localName = strtr(
            $jsonProp,
            ' !"#$%&\'()*+,/;<=>?@[\\]^`{|}~',
            '_------------.----------------'
        );

        /** - Prefix the result with underscore if it would start with a minus
         *  sign, dot or digit. */
        if (strpos('-.0123456789', $localName[0]) !== false) {
            $localName = "_$localName";
        }

        return $localName;
    }

    /// Convert a JSON pointer to a xml:id attribute
    public function jsonPtr2XmlId(string $jsonPtr): string
    {
        /** @throw alcamo:exception:SyntaxError if $jsonPtr does not start
         *  with a slash. */
        if ($jsonPtr[0] != '/') {
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $jsonPtr,
                    'atOffset' => 0,
                    'expectedOneOf' => '/',
                    'extraMessage' => 'invalid JSON pointer'
                ]
            );
        }

        /** Apply jsonProp2localName() to whatever follows the initial
         *  slash. */
        return $this->jsonProp2localName(substr($jsonPtr, 1));
    }
}
