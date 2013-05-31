<?php defined('SYSPATH') or die('No direct access allowed.');


Route::set('scrape', 'peruse(/<id>(/<id2>))')
    ->defaults(array(
        'controller' => 'peruse',
        'action'     => 'scrape',
    ));

Route::set('api', 'api(/<action>(/<id>(/<id2>)))')
    ->defaults(array(
        'directory'  => '',
        'controller' => 'api',
        'action'     => 'index',
    ));