<?php
/**
 * @defgroup plugins_paymethod_emspubpaddle Paddle Payment Plugin
 */

/**
 * @file plugins/paymethod/emspubpaddle/index.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_paymethod_emspubpaddle
 * @brief Wrapper for Paddle payment plugin.
 *
 */

require_once(__DIR__ . '/EmsPubPaddlePlugin.php');

return new \APP\plugins\paymethod\emspubpaddle\EmsPubPaddlePlugin();
