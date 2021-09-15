<?php

namespace alcamo\json;

/**
 * @brief Depth-first iterator for a JSON (sub)tree
 */
class RecursiveWalker implements \Iterator
{
    public const JSON_OBJECTS_ONLY = 1;

    private $startNode_;   ///< JsonNode

    protected $nextMethod_; ///< string

    private $currentStack_;     ///< array
    private $currentParent_;    ///< JsonNode|array
    private $currentIterator_;  ///< Iterator over parent props
    private $currentParentPtr_; ///< JSON pointer string

    private $currentKey_;  ///< JSON pointer string
    private $currentNode_; ///< mixed

    public function __construct(JsonNode $startNode, ?int $flags = null)
    {
        $this->startNode_ = $startNode;

        if ((int)$flags && self::JSON_OBJECTS_ONLY) {
            $this->nextMethod_ = 'jsonObjectsOnlyNext';
        } else {
            $this->nextMethod_ = 'simpleNext';
        }

        $this->rewind();
    }

    public function current()
    {
        return $this->currentNode_;
    }

    /// Return JSON pointer to current node
    public function key()
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
        /* Model the start node as the only child of an artificial
         * parent. This greatly simplies the implementation of next(). */

        $this->currentStack_ = [];
        $this->currentParent_ = [ $this->startNode_ ];
        $this->currentIterator_ =
            (new \ArrayObject($this->currentParent_))->getIterator();

        $this->currentKey_ = $this->startNode_->getJsonPtr();
        $this->currentNode_ = $this->startNode_;
    }

    public function valid(): bool
    {
        return isset($this->currentKey_);
    }

    /// next() implementation procesding to the next node, if any
    protected function simpleNext(): void
    {
        switch (true) {
            case $this->currentNode_ instanceof JsonNode:
                $children = get_object_vars($this->currentNode_);
            break;

            case is_array($this->currentNode_):
                $children = $this->currentNode_;
                break;

            default:
                $children = null;
        }

        // if current node has children, go down to first child
        if ($children) {
            $this->currentStack_[] = [
                $this->currentParent_,
                $this->currentIterator_,
                $this->currentParentPtr_
            ];

            $this->currentParent_ = $this->currentNode_;
            $this->currentIterator_ =
                (new \ArrayObject($children))->getIterator();
            $this->currentParentPtr_ = $this->currentKey_;
        } else {
            /// otherwise, go up until finding a level where there is a sibling
            for (
                $this->currentIterator_->next();
                $this->currentStack_ && !$this->currentIterator_->valid();
                $this->currentIterator_->next()
            ) {
                [
                    $this->currentParent_,
                    $this->currentIterator_,
                    $this->currentParentPtr_
                ] = array_pop($this->currentStack_);
            }

            /// exit iteration if no more siblings on any level
            if (!$this->currentIterator_->valid()) {
                $this->currentKey_ = null;
                $this->currentNode_ = null;
                return;
            }
        }

        $this->currentKey_ =
            ($this->currentParentPtr_ == '/'
             ? '/'
             : "$this->currentParentPtr_/")
            . str_replace(
                [ '~', '/' ],
                [ '~0', '~1' ],
                $this->currentIterator_->key()
            );

        $this->currentNode_ = $this->currentIterator_->current();
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
