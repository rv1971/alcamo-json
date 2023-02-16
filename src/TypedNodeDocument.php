<?php

namespace alcamo\json;

use alcamo\exception\{InvalidEnumerator, ProgramFlowException};

/**
 * @brief JSON document class that creates child nodes of specific classes
 *
 * Each non-abstract child class must define or inherit a public class
 * constant `CLASS_MAP`. The value of this constant must an associative array
 * mapping property names to class names or nested maps. Each class mentioned
 * in the map or the nested maps must itself define or inherit a `CLASS_MAP`
 * constant.
 *
 * While building the PHP document tree, when the value of a property of a
 * JSON object is a JSON object, it is represented in PHP as an instance of
 * the indicated class for that property.
 *
 * The property `*` applies to all properties which are not explicitely listed
 * in the map. An alcamo::exception::InvalidEnumerator exception is thrown
 * if a property having a JSON object value is not listed in the map and no
 * `*` entry exists.
 *
 * A nested map is an array whose values are again class names or nested
 * maps. When the value of a property is a JSON array, the nested map is used
 * to determine the classes to use for the single array elements. Again, `*`
 * stands for "any other array key".
 *
 * The value `#` can be used instead of a class name to indicate the
 * current class, which may be a child class of the class that defines
 * the class map.
 */
class TypedNodeDocument extends JsonDocument
{
    /// Class to use for $value at the position $jsonPtr
    public function getNodeClassToUse(JsonPtr $jsonPtr, object $value): string
    {
        /** Return JsonReference for any reference nodes. A node is considered
         *  a reference node iff is has a `$ref` property with a string
         *  value. */
        if (isset($value->{'$ref'}) && is_string($value->{'$ref'})) {
            return JsonReferenceNode::class;
        }

        $class = static::NODE_CLASS;

        foreach ($jsonPtr as $segment) {
            if (!isset($map)) {
                try {
                    $map = $class::CLASS_MAP;
                } catch (\Throwable $e) {
                    /** @throw alcamo::exception::ProgramFlowException if
                     *  a class lacks a class map. */
                    throw new ProgramFlowException(
                        "No CLASS_MAP in {class}",
                        0,
                        null,
                        [ 'atUri' => $this->getBaseUri(), 'class' => $class ]
                    );
                }
            }

            try {
                $childSpec = $map[$segment] ?? $map['*'];
            } catch (\Throwable $e) {
                $uri = $this->getBaseUri() . "#$jsonPtr";

                /** @throw alcamo::exception::InvalidEnumerator if no entry
                 *  is found in the class map. */
                throw (new InvalidEnumerator())->setMessageContext(
                    [
                        'value' => $segment,
                        'expectedOneOf' => array_keys($map),
                        'atUri' => $uri
                    ]
                );
            }

            if (is_array($childSpec)) {
                $map = $childSpec;
            } else {
                unset($map);

                if ($childSpec != '#') {
                    $class = $childSpec;
                }
            }
        }

        return $class;
    }
}
