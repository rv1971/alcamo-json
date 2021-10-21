<?php

namespace alcamo\json;

use alcamo\exception\{SyntaxError, Unsupported};
use alcamo\json\exception\NodeNotFound;

/**
 * @brief JSON document trait
 *
 * Implemented as a trait so that new document classes can be implemented as
 * descendent classes of JsonNode and use the present trait.
 */
trait JsonDocumentTrait
{
    private $documentFactory_; ///< JsonDocumentFactory

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
    public function getNode(string $jsonPtr)
    {
        if ($jsonPtr[0] != '/') {
            /** @throw alcamo::exception::SyntaxError if $jsonPtr does not
             *  start with a slash. */
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $jsonPtr,
                    'atOffset' => 0,
                    'extraMessage' => 'not a valid JSON pointer',
                    'atUri' => $this->getUri()
                ]
            );
        }

        $current = $this;
        $currentJsonPtr = '';

        for (
            $refToken = strtok($jsonPtr, '/');
            $refToken !== false;
            $refToken = strtok('/')
        ) {
            $currentJsonPtr .= "/$refToken";

            /** @throw alcamo::json::exception::NodeNotFound if there is no
             *  node for the given pointer. */

            if (is_object($current)) {
                $refToken =
                    str_replace([ '~1', '~0' ], [ '/', '~' ], $refToken);

                if (
                    !isset($current->$refToken)
                    && !property_exists($current, $refToken)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
                        ]
                    );
                }

                $current = $current->$refToken;
            } else {
                if (
                    !isset($current[$refToken])
                    && !array_key_exists($refToken, $current)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
                        ]
                    );
                }

                $current = $current[$refToken];
            }
        }

        return $current;
    }

    /// Set JSON node identified by JSON pointer to new node
    public function setNode(string $jsonPtr, $newNode): void
    {
        if ($jsonPtr[0] != '/') {
            /** @throw alcamo::exception::SyntaxError if $jsonPtr does not
             *  start with a slash. */
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $jsonPtr,
                    'atOffset' => 0,
                    'extraMessage' >= 'not a valid JSON pointer',
                    'atUri' => $this->getUri()
                ]
            );
        }

        if ($jsonPtr == '/') {
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
        $currentJsonPtr = '';

        for (
            $refToken = strtok($jsonPtr, '/');
            $refToken !== false;
            $refToken = strtok('/')
        ) {
            $currentJsonPtr .= "/$refToken";

            /** @throw alcamo::json::exception::NodeNotFound if there is no
             *  node for the given pointer. */

            if ($current->value instanceof JsonNode) {
                $refToken =
                    str_replace([ '~1', '~0' ], [ '/', '~' ], $refToken);

                if (
                    !isset($current->value->$refToken)
                    && !property_exists($current, $refToken)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
                        ]
                    );
                }

                $current = new ReferenceContainer($current->value->$refToken);
            } else {
                if (
                    !isset($current->value[$refToken])
                    && !array_key_exists($refToken, $current)
                ) {
                    throw (new NodeNotFound())->setMessageContext(
                        [
                            'inData' => $this,
                            'jsonPtr' => $currentJsonPtr,
                            'atUri' => $this->getUri()
                        ]
                    );
                }

                $current = new ReferenceContainer($current->value[$refToken]);
            }
        }

        $current->value = $newNode;
    }

    /// Get class that should be used to create a node
    public function getNodeClassToUse(string $jsonPtr, object $value): string
    {
        return JsonNode::class;
    }
}
