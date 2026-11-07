<?php
/**
 * This file is part of phloxcz/api-core.
 *
 * (c) Phlox <https://github.com/phloxcz>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phlox\ApiCore\Schema;

use Phlox\ApiCore\Documentation\SchemaBuilder;

/**
 * A page of records, the way the API returns a collection:
 *
 *     {"data": [...], "total": 42, "page": 2, "pageSize": 10, "from": 10, "to": 19}
 *
 * Describe it by `new Paged(User::class)`, and build it by `Paged::result()`, so the
 * page and its description can't differ.
 */
final class Paged implements SchemaDefinition
{
    public function __construct(
        public readonly SchemaDefinition|string $item,
    ) {
    }

    public function toSchema(SchemaBuilder $builder): array
    {
        return self::wrap($builder->buildDefinition($this->item));
    }

    /**
     * The schema of a page of items already described.
     *
     * @param array<string, mixed>|\stdClass $item
     * @return array<string, mixed>
     */
    public static function wrap(array|\stdClass $item): array
    {
        $index = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'required' => ['data', 'total', 'page', 'pageSize', 'from', 'to'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => $item],
                'total' => ['type' => 'integer', 'description' => 'Records matching the query, on all pages.'],
                'page' => ['type' => 'integer', 'description' => 'This page, from 1.'],
                'pageSize' => ['type' => 'integer'],
                'from' => $index + ['description' => 'Index of the first record on this page, from 0; null for a page past the end.'],
                'to' => $index + ['description' => 'Index of the last record on this page, from 0; null for a page past the end.'],
            ],
        ];
    }

    /**
     * @template T
     * @param list<T> $data Records of this page
     * @param int $total Records on all pages
     * @param int $offset Index of the first record of the page
     * @param int $pageSize From 1
     * @return array{data: list<T>, total: int, page: int, pageSize: int, from: ?int, to: ?int}
     */
    public static function result(array $data, int $total, int $offset, int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException("A page needs a size from 1, got {$pageSize}.");
        }

        $last = min($offset + $pageSize, $total) - 1;

        return [
            'data' => $data,
            'total' => $total,
            'page' => intdiv($offset, $pageSize) + 1,
            'pageSize' => $pageSize,
            'from' => $offset > $last ? null : $offset,
            'to' => $offset > $last ? null : $last,
        ];
    }
}
