<?php

namespace alcamo\json;

/**
 * @brief Depth-first iterator for a JSON (sub)tree
 */
class RecursiveWalker implements \Iterator
{
    public const JSON_OBJECTS_ONLY = 1; ///< Only return JSON object nodes
    public const OMIT_START_NODE   = 2; ///< Do not return the start node itself

    private $startNode_; ///< JsonNode
    private $flags_;     ///< int

    protected $nextMethod_; ///< string

    private $currentStack_;         ///< array
    private $currentParent_;        ///< JsonNode|ReferenceContainer
    private $currentChildKey_;      ///< string
    private $currentGenerator_;     ///< Generator for items of $currentParent_
    private $currentParentPtr_;     ///< JSON pointer string
    private $skipChildren_ = false; ///< bool

    private $currentKey_;  ///< JSON pointer string
    private $currentNode_; ///< mixed

    public function __construct(JsonNode &$startNode, ?int $flags = null)
    {
        $this->startNode_ =& $startNode;
        $this->flags_ = (int)$flags;

        if ($flags & self::JSON_OBJECTS_ONLY) {
            $this->nextMethod_ = 'jsonObjectsOnlyNext';
        } else {
            $this->nextMethod_ = 'simpleNext';
        }

        $this->rewind();
    }

    /// Current node in the JSON tree, may be of any type
    public function current()
    {
        return $this->currentNode_ instanceof ReferenceContainer
            ? $this->currentNode_->value
            : $this->currentNode_;
    }

    /// Return JSON pointer to current node
    public function key(): string
    {
        return $this->currentKey_;
    }

    public function next(): void
    {
        $method = $this->nextMethod_;
        $this->$method();
    }

    public function rewind(): void
    {
        /* Model the start node as the only child of an artificial parent
         * array. This greatly simplies the implementation of next(). */

        $parent = [ '' => $this->startNode_ ];

        $this->currentStack_ = [];
        $this->currentParent_ = new ReferenceContainer($parent);
        $this->currentGenerator_ = $this->iterateArray($parent);

        $this->currentKey_ = $this->startNode_->getJsonPtr();
        $this->currentNode_ = $this->startNode_;

        if ($this->flags_ & self::OMIT_START_NODE) {
            $this->next();
        }
    }

    public function valid(): bool
    {
        return isset($this->currentKey_);
    }

    public function iterateArray(array $data)
    {
        /* To allow for concurrent iterators, do not iterate the array
         * itself. */
        foreach (array_keys($data) as $key) {
            yield $key => is_array($data[$key])
                ? new ReferenceContainer($data[$key])
                : $data[$key];
        }
    }

    public function iterateObject(JsonNode $data)
    {
        foreach (get_object_vars($data) as $key => $item) {
            yield $key => is_array($item)
                ? new ReferenceContainer($data->$key)
                : $item;
        }
    }

    /// Key of current node within its parent
    public function getCurrentChildKey(): string
    {
        return $this->currentChildKey_;
    }

    /// do not iterate children of current node
    public function skipChildren(): void
    {
        $this->skipChildren_ = true;
    }

    /**
     * @brief Modify the document by replacing the current node
     *
     * The walker will then walk through the new node.
     */
    public function replaceCurrent($value): void
    {
        if ($this->currentNode_ === $this->startNode_) {
            $this->startNode_ = $value;
            $this->currentNode_ = $value;
            return;
        }

        $key = $this->currentChildKey_;

        if ($this->currentParent_ instanceof JsonNode) {
            $this->currentParent_->$key = $value;
        } else {
            $this->currentParent_->value[$key] = $value;
        }

        $this->currentNode_ = $value;
    }

    /// next() implementation proceeding to the next node, if any
    protected function simpleNext(): void
    {
        switch (true) {
            case $this->skipChildren_:
                $this->skipChildren_ = false;
                $generator = null;
                break;

            case $this->currentNode_ instanceof JsonNode:
                $generator = $this->iterateObject($this->currentNode_);
            break;

            case $this->currentNode_ instanceof ReferenceContainer:
                $generator = $this->iterateArray($this->currentNode_->value);
                break;

            default:
                $generator = null;
        }

        // if current node has children, go down to first child
        if (isset($generator) && $generator->valid()) {
            $this->currentStack_[] = [
                $this->currentParent_,
                $this->currentGenerator_,
                $this->currentParentPtr_
            ];

            $this->currentParent_ = $this->currentNode_;
            $this->currentGenerator_ = $generator;
            $this->currentParentPtr_ = $this->currentKey_;
        } else {
            /// otherwise, go up until finding a level where there is a sibling
            for (
                $this->currentGenerator_->next();
                $this->currentStack_ && !$this->currentGenerator_->valid();
                $this->currentGenerator_->next()
            ) {
                [
                    $this->currentParent_,
                    $this->currentGenerator_,
                    $this->currentParentPtr_
                ] = array_pop($this->currentStack_);
            }

            /// exit iteration if no more siblings on any level
            if (!$this->currentGenerator_->valid()) {
                // ensure that replaceCurrent() fails
                $this->currentParent_ = null;

                $this->currentKey_ = null;
                $this->currentNode_ = null;
                return;
            }
        }

        $this->currentChildKey_ = $this->currentGenerator_->key();

        $this->currentKey_ =
            ($this->currentParentPtr_ == '/'
             ? '/'
             : "$this->currentParentPtr_/")
            . str_replace(
                [ '~', '/' ],
                [ '~0', '~1' ],
                $this->currentChildKey_
            );

        $this->currentNode_ = $this->currentGenerator_->current();
    }

    /// next() implementation procesding to the next JSON object, if any
    protected function jsonObjectsOnlyNext(): void
    {
        do {
            $this->simpleNext();
        } while (
            isset($this->currentKey_)
            && !($this->currentNode_ instanceof JsonNode)
        );
    }
}
