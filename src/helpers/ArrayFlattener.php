<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\helpers;

/**
 * Turns the nested array a serializer produces into a flat list of strings to
 * translate, and puts the translations back where they came from.
 *
 * Paths are kept as PHP arrays rather than encoded into string keys, so field
 * handles or element keys containing dots, dashes or quotes cannot corrupt the
 * mapping.
 */
class ArrayFlattener
{
    /**
     * @param array $data Nested serializer output.
     * @return array{0: array<int, string>, 1: array<int, array>} [values, paths]
     */
    public static function flatten(array $data): array
    {
        $values = [];
        $paths = [];

        self::walk($data, [], $values, $paths);

        return [$values, $paths];
    }

    private static function walk(array $data, array $prefix, array &$values, array &$paths): void
    {
        foreach ($data as $key => $value) {
            $path = array_merge($prefix, [$key]);

            if (is_array($value)) {
                self::walk($value, $path, $values, $paths);
                continue;
            }

            // Only strings with something in them are worth a round trip.
            if (is_string($value) && trim($value) !== '') {
                $index = count($values);
                $values[$index] = $value;
                $paths[$index] = $path;
            }
        }
    }

    /**
     * Rebuild the nested structure from translated values.
     *
     * @param array<int, string> $values Translations keyed by flatten() index.
     * @param array<int, array> $paths Paths from flatten().
     */
    public static function unflatten(array $values, array $paths): array
    {
        $result = [];

        foreach ($values as $index => $value) {
            if (!isset($paths[$index])) {
                continue;
            }

            $cursor = &$result;

            foreach ($paths[$index] as $segment) {
                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }

                $cursor = &$cursor[$segment];
            }

            $cursor = $value;
            unset($cursor);
        }

        return $result;
    }
}
