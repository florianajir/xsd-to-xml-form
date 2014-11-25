<?php
/**
 * Created by PhpStorm.
 * User: fajir
 * Date: 25/11/14
 * Time: 12:11
 */

class Schema extends AppModel {
    public $validate = array(
        'title' => array(
            'rule' => 'notEmpty'
        ),
        'description' => array(
            'rule' => 'notEmpty'
        )
    );
}