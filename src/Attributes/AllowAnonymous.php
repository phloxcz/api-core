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
 * Opts a method or a whole controller out of authorization.
 *
 * On a method it always wins. On a controller it only applies to methods
 * without their own #[Authorize], and never overrides #[Authorize] on the same
 * controller or its parents.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class AllowAnonymous
{
}
