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

namespace Phlox\ApiCore\Types;

use Phlox\ApiCore\Attributes\Constraints\Constraint;

/**
 * One field of a DTO.
 */
final readonly class DtoField
{
    /**
     * @param list<Constraint> $constraints
     * @param array<string, mixed> $schema Additions from #[Schema]
     */
    public function __construct(
        public string $name,
        public Type $type,
        public bool $required,
        public bool $hasDefault,
        public mixed $default,
        public bool $inConstructor,
        public array $constraints,
        public array $schema,
        public string $description,
    ) {
    }
}
