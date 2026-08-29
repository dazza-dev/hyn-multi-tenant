<?php

/*
 * This file is part of the hyn/multi-tenant package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/hyn/multi-tenant
 */

namespace Hyn\Tenancy\Events\Websites;

use Hyn\Tenancy\Abstracts\AbstractEvent;

/**
 * The active tenant has been released, and none is active.
 *
 * The counterpart to Switched. It carries no website.
 */
class Forgotten extends AbstractEvent
{
}
