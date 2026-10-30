<?php

namespace App\Support;

/** Moves one item a step up (-1) or down (+1) in an ordered list. */
final class Reorder
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>|null The new order, or null when the item is missing or already at that end.
     */
    public static function swap(array $items, int|string|false $index, int $direction): ?array
    {
        if ($index === false) {
            return null;
        }
        $target = $index + ($direction < 0 ? -1 : 1);
        if (! isset($items[$index], $items[$target])) {
            return null;
        }
        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

        return $items;
    }

    /**
     * Same as swap(), finding the item by value (e.g. a row id).
     *
     * @param  list<int|string>  $ids
     * @return list<int|string>|null
     */
    public static function swapId(array $ids, int|string $id, int $direction): ?array
    {
        return self::swap($ids, array_search($id, $ids, true), $direction);
    }
}
