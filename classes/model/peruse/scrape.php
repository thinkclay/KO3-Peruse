<?php
class Model_Peruse_Scrape extends Mango
{
    protected $_fields = array(
        'name'           => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
            'unique'     => true,
        ),
        'state'          => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'county'          => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'contacts'        => array(
            'type'  => 'array'
        ),
        'booking_ids'    => array(
            'type'       => 'array',
            'min_length' => 1,
            'max_length' => 127,
        ),
    );

    protected $_db = 'busted';
}