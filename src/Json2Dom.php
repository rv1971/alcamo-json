<?php

namespace alcamo\json;

/**
 * @brief Convert a JSON structure to a generic DOM tree
 *
 * - The document element has the namespace @ref STRUCTURE_NS and the local
 *   name @ref DOCUMENT_LOCAL_NAME.
 * - Properties are converted to DOM elements whose namespace is @ref
 *   OBJECT_NS and whose local name is the name of the JSON property converted
 *   by jsonProp2LocalName().
 * - In properties whose value are arrays:
 *   - Sub-objects are converted to <s:item> elements where s resolves to the
 *     @ref STRUCTURE_NS namespace.
 *   - Sub-arrays are converted accordingly.
 *
 * When a tag name created by jsonProp2LocalName() differs from the property,
 * the element also has an attribute `name` that tells the original property.
 *
 * @warning The result does not distinguish between empty arrays and empty
 * objects.
 *
 * Among others, this can be used to generate an HTML representation of a JSON
 * object by converting it to XML and then applying an XSLT stylesheet.
 */
class Json2Dom
{
    public const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /// Namespace for DOM elements that represent a JSON object
    public const OBJECT_NS =
        'tag:rv1971@web.de,2021:alcamo-json:j2d/object';

    /// Namespace for DOM elements needed for structures, e.g. arrays
    public const STRUCTURE_NS =
        'tag:rv1971@web.de,2021:alcamo-json:j2d/structure';

    /// Local name of the document element
    public const DOCUMENT_LOCAL_NAME = 'document';

    public const JSON_PTR_ATTRS = 1;    ///< Flag to add jsonPtr attributes
    public const XML_ID_ATTRS = 2;      ///< Flag to add xml:id attributes
    public const ALWAYS_NAME_ATTRS = 4; ///< Flag to always add name attributes

    /// Convert PHP types to JSON types
    public const PHP_TYPE_2_JSON_TYPE = [
        'boolean' => 'boolean',
        'integer' => 'integer',
        'double' => 'number',
        'string' => 'string',
        'array' => 'array',
        'object' => 'object',
        'NULL' => 'null'
    ];

    private $flags_; ///< int

    public function __construct(?int $flags = null)
    {
        $this->flags_ = (int)$flags;
    }

    public function getFlags(): int
    {
        return $this->flags_;
    }

    public function convert(JsonDocument $jsonDocument): \DOMDocument
    {
        $domDocument = $this->createDocumentRoot();

        $this->append(
            $domDocument->documentElement,
            $jsonDocument->getRoot(),
            new JsonPtr()
        );

        if ($jsonDocument->getBaseUri() !== null) {
            $domDocument->documentElement->setAttributeNS(
                self::XML_NS,
                'xml:base',
                $jsonDocument->getBaseUri()
            );
        }

        return $domDocument;
    }

    public function append(
        \DOMNode $domNode,
        $value,
        JsonPtr $jsonPtr,
        ?string $nsName = null,
        ?string $qName = null,
        ?string $origName = null
    ): void {
        if (isset($nsName)) {
            $child = $domNode->ownerDocument->createElementNS($nsName, $qName);
            $domNode->appendChild($child);

            if (isset($origName)) {
                $child->setAttribute('name', $origName);
            } elseif (
                $nsName == static::OBJECT_NS
                && ($this->flags_ & self::ALWAYS_NAME_ATTRS)
            ) {
                $child->setAttribute('name', $child->localName);
            }

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
        } else {
            $child = $domNode;
        }

        $child->setAttribute(
            'type',
            static::PHP_TYPE_2_JSON_TYPE[gettype($value)]
        );

        switch (true) {
            case is_object($value):
                foreach ($value as $prop => $item) {
                    $localName = $this->jsonProp2LocalName($prop);

                    $origName = ($localName != $prop) ? $prop : null;

                    $this->append(
                        $child,
                        $item,
                        $jsonPtr->appendSegment($prop),
                        static::OBJECT_NS,
                        $localName,
                        $origName
                    );
                }

                break;

            case is_array($value):
                foreach ($value as $pos => $item) {
                    $this->append(
                        $child,
                        $item,
                        $jsonPtr->appendSegment($pos),
                        static::STRUCTURE_NS,
                        's:item'
                    );
                }

                break;


            default:
                if (isset($value)) {
                    $child->nodeValue = is_bool($value)
                        ? ['false', 'true'][(int)$value]
                        : $value;
                }
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
    public function jsonProp2LocalName(string $jsonProp): string
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
    public function jsonPtr2XmlId(JsonPtr $jsonPtr): string
    {
        /** Apply jsonProp2LocalName() to whatever follows the initial
         *  slash. */
        return $this->jsonProp2LocalName(substr($jsonPtr, 1));
    }

    protected function createDocumentRoot(): \DOMDocument
    {
        $domDocument = new \DOMDocument();

        /* Loading a DOM tree from XML is the only known method to add the
         * default namespace to the document element. */
        $domDocument->loadXML(
            '<?xml version="1.0"?><s:document '
            . 'xmlns="' . static::OBJECT_NS . '" '
            . 'xmlns:s="' . static::STRUCTURE_NS . '"/>'
        );

        return $domDocument;
    }
}
