<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Helpers\Array;

use InvalidArgumentException;

final class ArrayHelper
{
    /**
     * Setting nested value.
     */
    public static function setNestedValue(array $fromKeys, mixed $lastArrayValue, array $rootArray = []): array
    {
        if ($fromKeys === []) {
            throw new InvalidArgumentException('Value of "$fromKeys" cannot be an empty array');
        }

        $current = &$rootArray;

        foreach ($fromKeys as $value) {
            if (! isset($current[$value])) {
                $current[$value] = [];
            }

            $current = &$current[$value];
        }

        $current = $lastArrayValue;

        return $rootArray;
    }

    /**
     * Get nested value.
     * NOTE: The "null" value can match the value of the array `$array['key] = null`
     */
    public static function getNestedValue(array $inputArray, array $fromKeys): mixed
    {
        foreach ($fromKeys as $key) {
            if (! isset($inputArray[$key])) {
                return null;
            }

            $inputArray = $inputArray[$key];
        }

        return $inputArray;
    }
}