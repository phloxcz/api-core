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

namespace Phlox\ApiCore\Documentation;

/**
 * An attribute of a parameter that says more about the method than the signature can.
 */
interface DescribesMethod
{
    /**
     * Added to the description of the parameter.
     */
    public function describeParameter(): string;

    /**
     * @return array<string, mixed>|\stdClass|null JSON Schema of the parameter; null to read it from its type
     */
    public function describeParameterSchema(SchemaBuilder $builder): array|\stdClass|null;

    /**
     * @return array<string, mixed>|\stdClass|null JSON Schema of what the method returns; null when it doesn't say
     */
    public function describeResponse(SchemaBuilder $builder): array|\stdClass|null;
}
