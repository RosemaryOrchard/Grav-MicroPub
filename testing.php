<?php
/**
 * Created by PhpStorm.
 * User: rosemaryorchard
 * Date: 2019-01-10
 * Time: 06:43
 */

require_once 'config.php';

foreach (DESTINATION as $key => $value) {
    $mpDestinationOptions[] = [
        'uid' => $value['url'],
        'name' => $value['name'],

    ];
}

print_r($mpDestinationOptions);