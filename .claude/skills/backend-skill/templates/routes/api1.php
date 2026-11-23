<?php

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
|
| api1.php is the only skill-generated route file directly inside routes/.
| Every module route file, including Auth, User, and Documentation, must be
| generated inside routes/v1/ and required below.
|
| Example:
| require __DIR__ . '/v1/users.php';
|
*/

// Public API documentation endpoint — included in every project by default.
require __DIR__ . '/v1/documentation.php';
