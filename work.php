<?php
include_once __DIR__ . '/10-printer.php';

$kassa = new printer('http://10.1.1.57:16732');

print_r($kassa->checkConnect());

print_r($kassa->checkShift());
print_r($kassa->checkKKT());
$items = array();
for ($i=1;$i<4;$i++) {
    $items[] = array(
        'type' => 'position',
        'name' => 'Бананы'.$i,
        'price' => 1,
        'quantity' => 1,
        'amount' => 1,
        'tax' =>  array('type' => 'none')
    );
    $i++;
    $items[] = array(
        'type' => 'text',
        'text' => '--------------------------------',
        'alignment' => 'left',
        'font' => 0,
        'doubleWidth' => false,
        'doubleHeight' => false
    );
}

//~ print_r($items);
$kassa->operator = 'Иванов';

print_r($kassa->printSellPaper($items));
sleep(10);
print_r($kassa->printSellReturnPaper($items));

if ($kassa->isShiftOpen()) {
    $kassa->closeShift();
}
