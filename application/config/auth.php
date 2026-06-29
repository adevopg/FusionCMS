<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * @package FusionCMS
 * @since 8.3.2
 * @version 8.3.2
 * @link    https://github.com/FusionWowCMS/FusionCMS
 */

$config['account_encryption'] = 'SRP6'; // SPH, SRP, SRP6

$config['rbac'] = false;

$config['battle_net'] = true;

$config['battle_net_encryption'] = "SRP6_V2"; // SRP6_V2, SRP6_V1, SPH

$config['totp_secret'] = false;

$config['totp_secret_name'] = 'token_key'; // token_key, totp_secret

$config['TOTPMasterSecret'] = ""; // for totp_secret
