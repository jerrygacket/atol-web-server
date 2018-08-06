<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class printer
{
  private $client = '';
  private $connected = false;

  public $uri = 'http://127.0.0.1';
  public $operator = '';

  function __construct($uri = 'http://127.0.0.1') {
    $this->uri = $uri;
    $this->client = new Client([
      'base_uri' => $this->uri,
      'timeout'  => 600.0,
      'headers' => ['Content-Type' => 'application/json'],
    ]);
    try {
      $response = $this->getData('/stat/requests');
      $this->connected = (is_array($response) and array_key_exists('ready_count',$response));
    } catch (\Exception $e) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
  }

  private function getData($uri,$data=array()) {
    $response = $this->client->request('GET',$uri,$data);
    $body = $response->getBody();
    $jsonResponse=json_decode($body,true);
    return $jsonResponse;
  }

  private function postData($data) {
  	$response = $this->client->request('POST','/requests',['json' => $data]);
  	$body = $response->getBody();
  	$jsonResponse=json_decode($body,true);
  	return $jsonResponse;
  }

  private function checkStatus($uuid) {
    $not_ready = true;
    $counter = 0;
      while ($not_ready) {
        $response = $this->getData('/requests/'.$uuid);
        $not_ready = ($response['results'][0]['status'] != 'ready');
        sleep(1);
        $counter++;
        if ($counter > 30) {break;} //ждем 30 секунд и типа отваливаемся по таймауту
      }
	   if ($not_ready) {
       return 'Timeout';
     } else {
       return $response;
     }
  }

  //Запрос состояния очереди задач
  function checkConnect() {
    $response = $this->getData('/stat/requests');
    //$this->connected = (is_array($response) and array_key_exists('ready_count',$response));
    return $response;
  }

  //Запрос состояния ККТ
  function checkKKT() {
    if (!$this->connected) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
    $newId = exec('uuidgen -r');
    $task = array('uuid' => $newId, 'request' => array('type' => 'getDeviceStatus'));
    $response = $this->postData($task);
    if (!empty($response)) {print_r($response);}
    $response = $this->checkStatus($newId);
    return $response;
  }

  //Запрос состояния смены
  function checkShift() {
    if (!$this->connected) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
    $newId = exec('uuidgen -r');
    $task = array('uuid' => $newId, 'request' => array('type' => 'getShiftStatus'));
    $response = $this->postData($task);
    if (!empty($response)) {print_r($response);}
    $response = $this->checkStatus($newId);
    return $response;
  }

  function isShiftOpen() {
    if (!$this->connected) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
    $newId = exec('uuidgen -r');
    $task = array('uuid' => $newId, 'request' => array('type' => 'getShiftStatus'));
    $response = $this->postData($task);
    if (!empty($response)) {print_r($response);}
    $response = $this->checkStatus($newId);
    $ShiftStatus = ($response['results'][0]['result']['shiftStatus']['state'] == 'opened');
    return $ShiftStatus;
  }

  //Открытие смены
  function openShift($operator) {
    if (!$this->connected) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
    if ($this->isShiftOpen()) {
      echo 'Shift is open'.PHP_EOL;
      return false;
    }
    if ($this->operator == '') {
      echo 'No operator'.PHP_EOL;
      return false;
    }
    $newId = exec('uuidgen -r');
    $task = array(
    'uuid' => $newId,
    'request' => array(
        'type' => 'openShift',
        'operator' => array(
            'name' => $this->operator //Фамилия и должность оператора
            //'vatin' => '123654789507' //ИНН оператора
            )
        )
    );
    $response = $this->postData($task);
    if (!empty($response)) {print_r($response);}
    $response = $this->checkStatus($newId);
    return $response;
  }

  //Закрытие смены
  function closeShift() {
    if (!$this->connected) {
      echo 'Not connected'.PHP_EOL;
      return false;
    }
    if (!$this->isShiftOpen()) {
      echo 'Shift is closed'.PHP_EOL;
      return false;
    }
    $newId = exec('uuidgen -r');
    $task = array(
    'uuid' => $newId,
    'request' => array(
        'type' => 'closeShift',
        )
    );
    $response = $this->postData($task);
    if (!empty($response)) {print_r($response);}
    $response = $this->checkStatus($newId);
    return $response;
  }

  //печать чека
  function printReceiptPaper($items) {
    if (!$this->isShiftOpen()) {
      $this->openShift();
    }
    $task = array();
    $task['type'] = 'sell';
    $task['electronically'] = false;
    $task['ignoreNonFiscalPrintErrors'] = false;
    $task['operator'] = array('name' => $this->operator);
    $task['items'] = $items;

  }
}
