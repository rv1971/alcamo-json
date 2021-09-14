<?php

namespace alcamo\json;

/// Depth-first iterator for a JSON subtree
class RecursiveWalker implements \Iterator
{
    private $startNode_;   ///< JsonNode

    private $currentStack_;     ///< array
    private $currentParent_;    ///< JsonNode|array
    private $currentIterator_;  ///< Iterator over parent props
    private $currentParentPtr_; ///< JSON pointer string

    private $currentKey_;  ///< JSON pointer string
    private $currentNode_; ///< mixed

    public function __construct(JsonNode $startNode)
    {
        $this->startNode_ = $startNode;

        $this->rewind();
    }

    public function current()
    {
        return $this->currentNode_;
    }

    public function key()
    {
        return $this->currentKey_;
    }

    public function next(): void
    {
        // if current node has children, go down to first child
        if (
            ($this->currentNode_ instanceof JsonNode
             && get_object_vars($this->currentNode_))
            || (is_array($this->currentNode_) && $this->currentNode_)
        ) {
            $this->currentStack_[] = [
                $this->currentParent_,
                $this->currentIterator_,
                $this->currentParentPtr_
            ];

            $this->currentParent_ = $this->currentNode_;
            $this->currentIterator_ =
                (new \ArrayObject(
                    is_object($this->currentParent_)
                    ? get_object_vars($this->currentParent_)
                    : $this->currentParent_
                ))->getIterator();
            $this->currentParentPtr_ = $this->currentKey_;
        } else {
            /// otherwise, go up until finding a level where there is a sibling
            for (
                $this->currentIterator_->next();
                !$this->currentIterator_->valid() && $this->currentStack_;
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

    public function rewind(): void
    {
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
}
