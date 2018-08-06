# atol-web-server

объект и функции для работы с веб-сервером атол (начиная с версии драйвера 10.3.1.).

# Установка web-сервера атол на Debian

ТРЕБОВАНИЯ:
пустая виртуальная машина (lxc контейнер не подходит!!!) или пк

ДЕЙСТВИЯ :
### на локальной машине (в виртуалке):
	apt install default-jre-headless default-jdk-headless
    
### распаковать архив с драйверами 10 версии
    unzip 10.3.1.zip
    cd 10.3.1/installer/deb/
    
### установить пакеты веб-сервера
    dpkg -i libfptr10_10.3.1_amd64.deb
    dpkg -i fptr10-web-server_10.3.1_all.deb

### логи смотреть по адресу
    cd  /var/log/AtolFptrWebServer/
    
### адрес настроек http://<host>:16732/settings
    Общие настройки - активировать сервер
    Настройки связи с ККТ - Канал обмена с ККТ: TCP/IP
    Настройки связи с ККТ - ip-адрес <ipaddress>
  
  Вся документация есть в архиве с драйверами.

# Нюансы
- Для генерации уникальных uuid используется uuidgen:
	$newId = exec('uuidgen -r');
- Необходима установка Guzzle:
	composer require guzzlehttp/guzzle
