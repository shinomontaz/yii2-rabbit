<?php

namespace shinomontaz\yii2-rabbit;

use Yii;
use yii\base\Component;
use interfaces\IConsumer;

class Rabbit extends Component {
    private $_host;
    private $_user;
    private $_port;
    private $_password;
    private $_exchangeName;
    private $_exchangeType;
    private $_durable;
    private $_vhost;
    private $_worker;
    private $_queue;

    public $_chunkSize = 50;

    private $_connection;
    private $_channel;
    private $_exchange;
    private $_queues = [];

    public function __construct() { }

    public function __destruct() {
        if(  $this->_connection ) {
            $this->_connection->disconnect();
        }
    }

    private function getConnection() {
        if( !$this->_connection ) {
            $this->_connection = new \AMQPConnection();
            $this->_connection->setLogin($this->_user);
            $this->_connection->setPassword($this->_password);
            $this->_connection->setHost($this->_host);
            $this->_connection->setVhost($this->_vhost);

            $this->_connection->connect();
            if (!$this->_connection->isConnected()) {
                $this->_connection = null;
                return;
            }
        }
        return $this->_connection;
    }

    private function getExchange() {
        if( !$this->_exchange ) {
            $this->_exchange   = new \AMQPExchange( $this->getChannel() );
            $this->_exchange->setName( $this->_exchangeName );
            $this->_exchange->setType( $this->_exchangeType );
            if( $this->_durable ) {
                $this->_exchange->setFlags(\AMQP_DURABLE);
            }
            $this->_exchange->declareExchange();
        }
        return $this->_exchange;
    }

    private function getChannel() {
        if( !$this->_channel ) {
            $this->_channel    = new \AMQPChannel($this->getConnection());
        }
        return $this->_channel;
    }

    private function getQueue( $queueName ) {
        $this->getExchange();
        if( !isset($this->_queues[$queueName]) || !$this->_queues[$queueName] ) {
            $queue = new \AMQPQueue( $this->getChannel() );
            $queue->setName( $queueName );
            if( $this->_durable ) {
                $queue->setFlags(\AMQP_DURABLE);
            }
            $queue->declareQueue();
            $queue->bind( $this->_exchangeName, $queueName );
            $this->_queues[$queueName] = $queue;
        }

        return $this->_queues[$queueName];
    }

    public function schedule($message, $key)
    {
        $this->getQueue( $key );
        if( !$this->getExchange()->publish( (string)$message, $key ) ) {
            throw new Exception('Message not sent!');
        }
    }

    public function process($queue = '')
    {
        $i = 0;
        while ($i++ < $this->_chunkSize && $message = $this->getQueue( $queue )->get(\AMQP_AUTOACK)) {
            if (!$message) {
                echo "not envelope \n";
                return;
            }
            $processFlag = call_user_func($this->_worker, $message);
            print_r( '$processFlag: '.$processFlag."\n" );
        }
    }

    public function setHost( $_host ) {
        $this->_host = $_host;
    }
    public function setUser( $_user ) {
        $this->_user = $_user;
    }
    public function setPort( $_port ) {
        $this->_port = $_port;
    }
    public function setDurable( $_durable ) {
        $this->_durable = $_durable;
    }
    public function setPassword( $_password ) {
        $this->_password = $_password;
    }
    public function setExchange( $_exchange = [] ) {
        $this->_exchangeName = $_exchange['name'];
        $this->_exchangeType = $_exchange['type'];
    }
    public function setQueues( $_queueNames ) {
        $this->_queueNames = $_queueNames;
    }
    public function setVhost( $_vhost ) {
        $this->_vhost = $_vhost;
    }
    public function setWorker( $_worker ) {
        if (!class_exists($_worker)) {
            $callbackClass = \Yii::$container->get($_worker);
        } else {
            $callbackClass = new $_worker();
        }
        if (!($callbackClass instanceof IConsumer)) {
            throw new \Exception("{$_worker} should implements IConsumer.");
        }
        $this->_worker = [$callbackClass, 'execute'];
    }
}