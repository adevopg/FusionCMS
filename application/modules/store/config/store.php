<?php

$config['store_subject'] = "Item delivery!";
$config['store_body'] = "Thank you for supporting our server, here are your ordered items!";
$config['success_message'] = "Your order has been processed. You may need to log out to receive your items. In case you don't receive your order with in 5 minutes, please contact a game master.";

$config['minimize_groups_by_default'] = true;

/**
 * Share the in-game BattlePay catalog with the web store.
 * When true, the store reads its catalog from the shared `battlepay_product`
 * table (on the realm "account"/auth database) instead of `store_items`,
 * grouping products by their `category`. Prices are read as donation points.
 */
$config['store_use_battlepay'] = true;
$config['battlepay_default_icon'] = 'inv_misc_gift_02';
$config['battlepay_default_quality'] = 4;
