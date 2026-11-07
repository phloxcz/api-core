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
 * Bytes of a file, such as a PDF:
 *
 *     #[Response(200, new Binary, contentType: 'application/pdf')]
 */
final class Binary implements SchemaDefinition
{
    public function toSchema(SchemaBuilder $builder): array
    {
        return ['type' => 'string', 'format' => 'binary'];
    }
}
