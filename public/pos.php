<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();

redirect('beverage_pos.php');
