<?php

namespace alcamo\json;

use alcamo\exception\{SyntaxError, Unsupported};
use alcamo\json\exception\NodeNotFound;
use Psr\Http\Message\UriInterface;

/**
 * @brief JSON document trait
 *
 * Implemented as a trait so that new document classes can be implemented as
 * descendent classes of JsonNode and use the present trait.
 */
trait JsonDocumentTrait
{
    private $baseUri_;         ///< ?UriInterface
    private $documentFactory_; ///< JsonDocumentFactory

    /**
     * @brief Construct a top-level JsonNode
     *
     * The signature prevents passing a wrong $ownerDocument of $jsonPtr to
     * the JsonNode constructor.
     */
    public function __construct(
        object $data,
        ?UriInterface $baseUri = null
    ) {
        parent::__construct($data, $this, new JsonPtr());

        $this->baseUri_ = $baseUri;
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
        $current = $this;
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
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
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
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
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
        if ($jsonPtr->isRoot()) {
            /** @throw alcamo::exception::Unsupported when attempting to
             *  replace the root node. */
            throw (new Unsupported())->setMessageContext(
                [
                    'feature' => 'replacement of root node',
                    'atUri' => "{$this->getUri()}#/"
                ]
            );
        }

        $current = new ReferenceContainer($this);
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
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
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
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
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
        return JsonNode::class;
    }
}
