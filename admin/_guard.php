<?php
/**
 * Include at the top of EVERY admin page, before any output.
 *
 * helpers.php starts the session with the default cookie path (/), so the
 * storefront session carries into /admin/ with no extra configuration.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/pages.php';

require_admin();

/** @var array $ADMIN_USER The authenticated admin. */
$ADMIN_USER = current_user();
