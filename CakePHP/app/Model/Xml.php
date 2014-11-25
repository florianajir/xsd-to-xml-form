<?php

class Xml extends AppModel {
    public $validate = array(
        'title' => array(
            'rule' => 'notEmpty'
        ),
        'content' => array(
            'rule' => 'notEmpty'
        ),
//        'schema' => array(
//            'rule' => 'notEmpty'
//        )
    );
}