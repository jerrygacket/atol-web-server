<?php
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class AtolPrinter
{
    private $client = '';
    private $connected = false;

    public $uri = '';
    public $operator = '';
    public $status = false;
    public $error = 1;

    function __construct($uri = 'http://127.0.0.1:16732') {
        $this->uri = $uri;
    }

    // чтение даных из принтера
    private function getData($uri,$data=array()) {
        $response = $this->client->request('GET',$uri,$data);
        $body = $response->getBody();
        $jsonResponse=json_decode($body,true);
        return $jsonResponse;
    }

    // запись данных в принтер
    private function postData($data) {
        $response = $this->client->request('POST','/requests',['json' => $data]);
        $body = $response->getBody();
        $jsonResponse=json_decode($body,true);
        return $jsonResponse;
    }

    // ожидание окончания задачи с id = $uuid
    // и проверка результата выполнения
    private function checkStatus($uuid) {
        $not_ready = true;
        $this->error = 1;
        $counter = 0;
        while ($not_ready) {
            $response = $this->getData('/requests/'.$uuid);
            $not_ready = ($response['results'][0]['status'] != 'ready');
            sleep(1);
            $counter++;
            if ($counter > 30) {break;} //ждем 30 секунд и типа отваливаемся по таймауту
        }
        if ($not_ready) {
          return 'Timeout'.PHP_EOL;
        } else {
          $this->status = true;
          $this->error = $response['results'][0]['errorCode'] ?? 1;
          return $response;
        }
    }

    // проверка подключения к принтеру и
    // Запрос состояния очереди задач
    function connect() {
        if ($this->connected) {
            echo 'Connected: '.$this->uri.PHP_EOL;
            return true;
        }

        if ($this->uri == '') {
            echo 'Error: no uri'.PHP_EOL;
            return false;
        }

        $this->client = new Client([
            'base_uri' => $this->uri,
            'timeout'  => 600.0,
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        try {
            $response = $this->getData('/stat/requests');
            $this->connected = (is_array($response) and array_key_exists('ready_count',$response));
            return true;
        } catch (\Exception $e) {
            echo 'Error: Not connected. '.$e.PHP_EOL;
            return false;
        }
    }

    // Запрос состояния очереди задач
    function checkConnect() {
      if (!$this->connected) {
        echo 'Not connected'.PHP_EOL;
        return false;
      }
      $response = $this->getData('/stat/requests');
      //$this->connected = (is_array($response) and array_key_exists('ready_count',$response));
      return $response;
    }

    // Запрос состояния ККТ
    function checkKKT() {
        if (!$this->connected) {
            echo 'Not connected'.PHP_EOL;
            return false;
        }
        $newId = exec('uuidgen -r');
        $task = array('uuid' => $newId, 'request' => array('type' => 'getDeviceStatus'));
        $response = $this->postData($task);
        //if (!empty($response)) {print_r($response);}
        $response = $this->checkStatus($newId);
        return $response;
    }

    // Запрос состояния смены
    // удобно для дебага
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

    // Запрос состояния смены
    // для прода
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
    function openShift() {
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
//******************************************************************************
    //печать чека прихода на бумаге
    function printSellPaper($items) {
        if (!$this->isShiftOpen()) {
            $this->openShift();
            if (!$this->isShiftOpen()) {
                echo 'Error open shift'.PHP_EOL;
                return false;
            }
        }

        //~ "clientInfo": {
        //~ "emailOrPhone": "+79161234567"
        //~ },

        $receipt = array();
        $receipt['type'] = 'sell';
        //$task['electronically'] = false;
        $receipt['ignoreNonFiscalPrintErrors'] = false;
        //~ $receipt['taxationType'] = 'usnIncome';
        // $receipt['operator']['name'] = $this->operator;
        $receipt['items'] = $items;

        $total = 0;
        foreach ($items as $item) {
            if (array_key_exists('price',$item) and array_key_exists('quantity',$item)) {
                $total += $item['price'] * $item['quantity'];
            }
        }

        $receipt['payments'] = array(array('type' => 'electronically', 'sum' => $total));
        $receipt['total'] = $total;

        $newId = exec('uuidgen -r');
        $task = array('uuid' => $newId, 'request' => $receipt);
        $response = $this->postData($task);
        if (!empty($response)) {print_r($response);}
        $response = $this->checkStatus($newId);
        return $response;
    }
//******************************************************************************
// чек возврата прихода на бумаге
    function printSellReturnPaper($items) {
        if (!$this->isShiftOpen()) {
            $this->openShift();
            if (!$this->isShiftOpen()) {
                echo 'Error open shift'.PHP_EOL;
                return false;
            }
        }
        $receipt = array();
        $receipt['type'] = 'sellReturn';
        //$task['electronically'] = false;
        $receipt['ignoreNonFiscalPrintErrors'] = false;
        //~ $receipt['taxationType'] = 'usnIncome';
        // $receipt['operator'] = array('name' => $this->operator);
        $receipt['items'] = $items;

        $total = 0;
        foreach ($items as $item) {
            if (array_key_exists('price',$item) and array_key_exists('quantity',$item)) {
                $total += $item['price'] * $item['quantity'];
            }
        }

        $receipt['payments'] = array(array('type' => 'electronically', 'sum' => $total));
        $receipt['total'] = $total;

        $newId = exec('uuidgen -r');
        $task = array('uuid' => $newId, 'request' => $receipt);
        $response = $this->postData($task);
        if (!empty($response)) {print_r($response);}
        $response = $this->checkStatus($newId);
        return $response;
    }
//******************************************************************************
    // чек расхода на бумаге
    function printBuyPaper($items) {
        // if (!$this->isShiftOpen()) {
        //   $this->openShift();
        // }
    }
//******************************************************************************
    // чек возврата расхода на бумаге
    function printBuyReturnPaper($items) {
        // if (!$this->isShiftOpen()) {
        //   $this->openShift();
        // }
    }

    //******************************************************************************
    //чек прихода без печати на ленте (но почему-то все равно печатает на ленте)
    function printSellElectro($items, $clientInfo) {
        if (!$this->isShiftOpen()) {
            $this->openShift();
            if (!$this->isShiftOpen()) {
                echo 'Error open shift'.PHP_EOL;
                return false;
            }
        }

        $total = 0;
        foreach ($items as $item) {
            if (array_key_exists('price',$item) and array_key_exists('quantity',$item)) {
                $total += $item['price'] * $item['quantity'];
            }
        }

        $receipt = array(
            'type' => 'sell',
            'electronically' => true, // здесь говорим, что без печати на ленте
            'clientInfo' => array(
                'emailOrPhone' => $clientInfo // обязательно в этом случае (см. документацию)
            ),
            // 'ignoreNonFiscalPrintErrors' => false,
            'operator' => array(
                'name' => $this->operator
            ),
            'items' => $items,
            'payments' => array(array(
                'type' => 'electronically',
                'sum' => $total
            )),
            'total' => $total,
        );

        $newId = exec('uuidgen -r');
        $task = array('uuid' => $newId, 'request' => $receipt);
        $response = $this->postData($task);
        //if (!empty($response)) {print_r($response);}
        $response = $this->checkStatus($newId);
        return $response;
    }
//******************************************************************************
// чек возврата прихода без печати на ленте
    function printSellReturnElectro($items, $clientInfo) {
        if (!$this->isShiftOpen()) {
            $this->openShift();
            if (!$this->isShiftOpen()) {
                echo 'Error open shift'.PHP_EOL;
                return false;
            }
        }

        $total = 0;
        foreach ($items as $item) {
            if (array_key_exists('price',$item) and array_key_exists('quantity',$item)) {
                $total += $item['price'] * $item['quantity'];
            }
        }

        $receipt = array(
            'type' => 'sellReturn',
            'electronically' => true, // здесь говорим, что без печати на ленте
            'clientInfo' => array(
                'emailOrPhone' => $clientInfo // обязательно в этом случае (см. документацию)
            ),
            //'ignoreNonFiscalPrintErrors' => false,
            'operator' => array(
                'name' => $this->operator
            ),
            'items' => $items,
            'payments' => array(array(
                'type' => 'electronically',
                'sum' => $total
            )),
            'total' => $total,
        );

        $newId = exec('uuidgen -r');
        $task = array('uuid' => $newId, 'request' => $receipt);
        $response = $this->postData($task);
        if (!empty($response)) {print_r($response);}
        $response = $this->checkStatus($newId);
        return $response;
    }
}
