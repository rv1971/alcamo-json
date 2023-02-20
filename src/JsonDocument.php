<?php

namespace alcamo\json;

use alcamo\json\exception\NodeNotFound;
use Psr\Http\Message\UriInterface;

/**
 * @brief JSON document
 */
class JsonDocument
{
    /// Default class to use to create a JSON object node
    public const NODE_CLASS = JsonNode::class;

    /// Default class to use to create a document factory
    public const DOCUMENT_FACTORY_CLASS = JsonDocumentFactory::class;

    private $root_;            ///< JsonNode, array or primitive type
    private $baseUri_;         ///< ?UriInterface
    private $documentFactory_; ///< JsonDocumentFactory

    public function __construct($jsonData, ?UriInterface $baseUri = null)
    {
        $this->root_ = $this->createNode($jsonData, new JsonPtr());
        $this->baseUri_ = $baseUri;
    }

    public function __clone()
    {
        if ($this->root_ instanceof JsonNode) {
            $this->root_ = JsonNode::importObjectNode(
                $this,
                $this->root_,
                new JsonPtr(),
                JsonNode::COPY_UPON_IMPORT
            );
        } elseif (is_array($this->root_)) {
            $this->root_ = JsonNode::importArrayNode(
                $this,
                $this->root_,
                new JsonPtr(),
                JsonNode::COPY_UPON_IMPORT
            );
        } /* else root is a primitive type */

        if (isset($this->baseUri_)) {
            $this->baseUri_ = clone $this->baseUri_;
        }

        $this->documentFactory_ = null;
    }

    public function &getRoot()
    {
        return $this->root_;
    }

    /// Base URI, if specified
    public function getBaseUri(): ?UriInterface
    {
        return $this->baseUri_;
    }

    public function getDocumentFactory(): JsonDocumentFactory
    {
        if (!isset($this->documentFactory_)) {
            $class = static::DOCUMENT_FACTORY_CLASS;

            $this->documentFactory_ = new $class();
        }

        return $this->documentFactory_;
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
    public function getNodeClassToCreate(
        JsonPtr $jsonPtr,
        object $value
    ): string {
        /** Return JsonReference for any reference nodes, otehrwise @ref
         *  NODE_CLASS. A node is considered a reference node iff is has a
         *  `$ref` property with a string value. */
        return isset($value->{'$ref'}) && is_string($value->{'$ref'})
            ? JsonReferenceNode::class
            : static::NODE_CLASS;
    }

    /**
     * @brief Create a node in a JSON tree
     * - If $value is an object, call getNodeClassToCreate() and __construct().
     * - Else, if $value is an array, create an array, calling createNode()
     *   recursively.
     * - Else, use $value as-is.
     */
    public function createNode(
        $value,
        JsonPtr $jsonPtr,
        ?JsonNode $parent = null
    ) {
        switch (true) {
            case is_object($value):
                $class = $this->getNodeClassToCreate($jsonPtr, $value);

                return new $class($value, $this, $jsonPtr, $parent);

            case is_array($value):
                $result = [];

                foreach ($value as $prop => $subValue) {
                    $result[] = $this->createNode(
                        $subValue,
                        $jsonPtr->appendSegment($prop)
                    );
                }

                return $result;

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
        $this->root_ = $this->root_->resolveReferences($resolver);
    }

    /// Check internal structure, for debugging
    public function checkStructure(): void
    {
        foreach (
            new RecursiveWalker(
                $this,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $pair
        ) {
            [ $jsonPtr, $node ] = $pair;

            if ($node->getOwnerDocument() !== $this) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong owner document. */
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'inData' => (string)$node,
                        'atUri' => (string)$node->getUri(),
                        'extraMessage' =>
                        "\$ownerDocument_ differs from document owning this node"
                    ]
                );
            }

            if ((string)$node->getJsonPtr() != (string)$jsonPtr) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong JSON pointer. */
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'inData' => (string)$node,
                        'atUri' => (string)$node->getUri(),
                        'extraMessage' =>
                        "node's JSON pointer \"{$node->getJsonPtr()}\" "
                        . "differs from actual position \"$jsonPtr\""
                    ]
                );
            }

            $parentJsonPtr = $jsonPtr->getParent();

            if (isset($parentJsonPtr)) {
                $parent = $this->getNode($parentJsonPtr);

                if ($parent instanceof JsonNode) {
                    if ($node->getParent() !== $parent) {
                        /** @throw alcamo::exception::DataValidationFailed if
                         *  a node's parent is not the parent node it should
                         *  be */
                        throw (new DataValidationFailed())->setMessageContext(
                            [
                                'inData' => (string)$node,
                                'atUri' => (string)$node->getUri(),
                                'extraMessage' =>
                                "node's parent "
                                . ($node->getParent()
                                   ? "\"{$node->getParent()}\""
                                   : "null")
                                . " differs from correct parent at $parentJsonPtr"
                            ]
                        );
                    }
                } else {
                    if ($node->getParent() !== null) {
                        /** @throw alcamo::exception::DataValidationFailed if
                         *  a node's parent is not null while it should be
                         *  null because the parent is not a JSON object */
                        throw (new DataValidationFailed())->setMessageContext(
                            [
                                'inData' => (string)$node,
                                'atUri' => (string)$node->getUri(),
                                'extraMessage' =>
                                "node's parent is not null while parent is not a JSON object"
                            ]
                        );
                    }
                }
            } else {
                if ($node->getParent() !== null) {
                    /** @throw alcamo::exception::DataValidationFailed if a
                     *  node's parent is not null while it should be null
                     *  because the current node os the root node */
                    throw (new DataValidationFailed())->setMessageContext(
                        [
                            'inData' => (string)$node,
                            'atUri' => (string)$node->getUri(),
                            'extraMessage' =>
                            "\$parent_ of root node is not null"
                        ]
                    );
                }
            }
        }
    }
}
