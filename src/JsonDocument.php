<?php

namespace alcamo\json;

use alcamo\json\exception\NodeNotFound;
use Psr\Http\Message\UriInterface;

/**
 * @brief JSON document
 */
class JsonDocument
{
    public const NODE_CLASS = JsonNode::class;

    private $root_;            ///< ?JsonNode
    private $baseUri_;         ///< ?UriInterface
    private $documentFactory_; ///< JsonDocumentFactory

    /**
     * Calls setRoot() if $root is not `null`, so that derived classes which
     * need to run some initialization code depending $root may simply
     * redefine setRoot().
     */
    public function __construct(
        ?JsonNode $root = null,
        ?UriInterface $baseUri = null
    ) {
        $this->baseUri_ = $baseUri;

        if (isset($root)) {
            $this->setRoot($root);
        }
    }

    public function __clone()
    {
        $jsonPtr = new JsonPtr();

        $this->root_ = (new JsonNode((object)[], $this, $jsonPtr))
            ->importObjectNode(
                $this->root_,
                $jsonPtr,
                JsonNode::COPY_UPON_IMPORT
            );

        $this->baseUri_ = isset($this->baseUri_) ? clone $this->baseUri_ : null;
        $this->documentFactory_ = null;
    }

    public function &getRoot(): ?JsonNode
    {
        return $this->root_;
    }

    public function setRoot(JsonNode $root): void
    {
        $this->root_ = $root;
    }

    /// Base URI, if specified
    public function getBaseUri(): ?UriInterface
    {
        return $this->baseUri_;
    }

    public function getDocumentFactory(): JsonDocumentFactory
    {
        if (!isset($this->documentFactory_)) {
            $this->documentFactory_ = new JsonDocumentFactory();
        }

        return $this->documentFactory_;
    }

    public function setDocumentFactory(
        JsonDocumentFactory $documentFactory
    ): void {
        $this->documentFactory_ = $documentFactory;
    }

    /// Get JSON node identified by JSON pointer
    public function getNode(JsonPtr $jsonPtr)
    {
        $current = $this->root_;
        $currentJsonPtr = new JsonPtr();

        foreach ($jsonPtr as $segment) {
            $currentJsonPtr = $currentJsonPtr->appendSegment($segment);

            /** @throw alcamo::json::exception::NodeNotFound if there is no
             *  node for the given pointer. */

            if (is_object($current)) {
                if (
                    !isset($current->$segment)
                    && !property_exists($current, $segment)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $current,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getBaseUri()
                        ]
                    );
                }

                $current = $current->$segment;
            } else {
                if (
                    !isset($current[$segment])
                    && !array_key_exists($segment, $current)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $current,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getBaseUri()
                        ]
                    );
                }

                $current = $current[$segment];
            }
        }

        return $current;
    }

    public function setNode(JsonPtr $jsonPtr, $newNode): void
    {
        $current = new ReferenceContainer($this->root_);
        $currentJsonPtr = new JsonPtr();

        foreach ($jsonPtr as $segment) {
            $currentJsonPtr = $currentJsonPtr->appendSegment($segment);

            /** @throw alcamo::json::exception::NodeNotFound if there is no
             *  node for the given pointer. */

            if ($current->value instanceof JsonNode) {
                if (
                    !isset($current->value->$segment)
                    && !property_exists($current, $segment)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $current->value,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getBaseUri()
                        ]
                    );
                }

                $current = new ReferenceContainer($current->value->$segment);
            } else {
                if (
                    !isset($current->value[$segment])
                    && !array_key_exists($segment, $current)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $current->value,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getBaseUri()
                        ]
                    );
                }

                $current = new ReferenceContainer($current->value[$segment]);
            }
        }

        $current->value = $newNode;
    }

    /// Get class that should be used to create a node
    public function getNodeClassToUse(JsonPtr $jsonPtr, object $value): string
    {
        /** Return JsonReference for any reference nodes, otehrwise @ref
         *  NODE_CLASS. A node is considered a reference node iff is has a
         *  `$ref` property with a string value. */
        return isset($value->{'$ref'}) && is_string($value->{'$ref'})
            ? JsonReferenceNode::class
            : static::NODE_CLASS;
    }

    /**
     * @brief Create a node in a JSON tree
     * - If $value is a nonempty numerically-indexed array, create an array.
     * - Else, if $value is an object or associative array, call createNode()
     *   recursively.
     * - Else, use $value as-is.
     *
     * @sa [How to check if PHP array is associative or sequential?](https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential)
     */
    public function createNode(
        $value,
        JsonPtr $jsonPtr,
        ?JsonNode $parent = null
    ) {
        switch (true) {
            case is_array($value)
                && ($value == []
                    || ((isset($value[0]) || array_key_exists(0, $value))
                        && array_keys($value) === range(0, count($value) - 1))):
                $result = [];

                foreach ($value as $prop => $subValue) {
                    $result[] = $this->createNode(
                        $subValue,
                        $jsonPtr->appendSegment($prop)
                    );
                }

                return $result;

            case is_object($value):
                $class = $this->getNodeClassToUse($jsonPtr, $value);

                return new $class($value, $this, $jsonPtr, $parent);

            default:
                return $value;
        }
    }

    /**
     * @param $resolver A ReferenceResolver object, or one of the
     * ReferenceResolver constants which is the used to construct a
     * ReferenceResolver object.
     */
    public function resolveReferences(
        $resolver = ReferenceResolver::RESOLVE_ALL
    ): void {
        if (!($resolver instanceof ReferenceResolver)) {
            $resolver = new ReferenceResolver($resolver);
        }

        $this->root_ = $resolver->resolve($this->root_);
    }
}
