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

namespace Phlox\ApiCore;

/**
 * Object that can be constructed from an API parameter.
 *
 * An \InvalidArgumentException thrown from apiConstruct() is reported to the
 * client as invalid parameters.
 */
interface ApiConstructible
{
    /**
     * @param mixed $value Raw value of the API parameter
     * @return object Instance of the implementing class; not declared natively so that implementations stay free to declare it
     */
    public static function apiConstruct(mixed $value);
}
