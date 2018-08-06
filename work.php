<?php
include_once __DIR__ . '/printer.php';

$kassa = new printer('http://10.1.1.57:16732');

print_r($kassa->checkConnect());

print_r($kassa->checkShift());
print_r($kassa->checkKKT());

//~ $items=array();
//~ for ($i=1;$i<10;$i+2) {
    //~ $items[$i]['type'] = 'position';
    //~ $items[$i]['name'] = 'Бананы'.$i;
    //~ $items[$i]['price'] = 10+$i;
    //~ $items[$i]['quantity'] = 1;
    //~ $items[$i]['tax'] = array('type' => 'none');
    
    //~ $items[$i+1]['type'] = 'text';
    //~ $items[$i+1]['text'] = '--------------------------------';
    //~ $items[$i+1]['alignment'] = 'left';
    //~ $items[$i+1]['font'] = 0;
    //~ $items[$i+1]['doubleWidth'] = false;
    //~ $items[$i+1]['doubleHeight'] = false;
//~ }

//~ print_r($items);
