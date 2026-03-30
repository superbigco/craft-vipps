<?php

declare(strict_types=1);

namespace superbig\vipps\exceptions;

use RuntimeException;

/**
 * Thrown when Vipps API authentication fails.
 * This includes token fetch failures and invalid credentials.
 */
class VippsAuthenticationException extends RuntimeException
{
}
