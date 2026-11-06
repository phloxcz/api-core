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

namespace Phlox\ApiCore\Attributes;

use Attribute;

/**
 * A class describing data of the API: built from a request when it's a parameter,
 * turned into JSON when it's returned, and documented in both cases.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Dto
{
    /**
     * Whether a response may hold only some of its fields, so none of them is documented as required.
     */
    public function isPartial(): bool
    {
        return false;
    }

    /**
     * Whether it can be built from a request, rather than only describing what goes out.
     */
    public function isInput(): bool
    {
        return true;
    }
}
