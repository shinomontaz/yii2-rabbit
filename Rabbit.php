<?php

namespace shinomontaz\rabbit;

use Yii;
use yii\base\Component;
use interfaces\IConsumer;

class Rabbit extends Component {
    public $_chunkSize = 50;
    
    private $_host;
    private $_user;
    private $_port;
    private $_password;
    private $_vhost;
    private $_exchanges = [];
    private $_queues    = [];
    private $_workers   = [];
    private $_workerDefault;

    private $_connection;
    private $_channel;

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

    private function _getExchange( $name ) {
        return $this->_exchanges[$name];
    }

    private function getChannel() {
        if( !$this->_channel ) {
            $this->_channel    = new \AMQPChannel($this->getConnection());
        }
        return $this->_channel;
    }

    private function getQueue( $queueName ) {
        return $this->_queues[$queueName];
    }

    public function schedule($message, $exchange, $key)
    {
        if( !$this->getExchange( $exchange )->publish( (string)$message, $key ) ) {
            throw new \Exception('Message not sent!');
        }
    }
    
    public function process( $queueName )
    {
      if( !isset( $this->_workers[$queueName] ) && !isset( $this->_workerDefault ) ) {
        throw new \Exception('No worker attache');
      }
      $worker = isset( $this->_workers[$queueName] ) ? $this->_workers[$queueName] : $this->_workerDefault;
      $i = 0;
      while ($i++ <= $this->_chunkSize && $message = $this->getQueue( $name )->get(\AMQP_AUTOACK)) {
        call_user_func($worker, $message);
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
    public function setVhost( $_vhost ) {
        $this->_vhost = $_vhost;
    }
    
    public function seLayout( $_layout ) {
      foreach( $_layout as $exchangeName => $info ) {
        $this->_exchanges[$exchangeName] = new \AMQPExchange( $this->getChannel() );
        $this->_exchanges[$exchangeName]->setName( $exchangeName );
        $this->_exchanges[$exchangeName]->setType( $info['type'] );
        if( $info['durable'] ) {
            $this->_exchanges[$exchangeName]->setFlags(\AMQP_DURABLE);
        }
        $this->_exchanges[$exchangeName]->declareExchange();
        
        foreach( $info['queues'] as $queueName => $queueData ) {
          $queue = new \AMQPQueue( $this->getChannel() );
          $queue->setName( $queueName );
          if( $queueData['durable'] ) {
              $queue->setFlags(\AMQP_DURABLE);
          }
          $queue->declareQueue();
          $queue->bind( $exchangeName, $queueName );
          $this->_queues[$queueName] = $queue;
          if( isset( $info['worker'] ) ) {
            $callbackClass = new ($info['worker'])();
            $this->_workers[ $queueName ] = $this->_createWorker( $info['worker'] );//[$callbackClass, 'execute'];
          }
        }
        
      }
    }

    public function setWorker( $_worker ) {
        $callbackClass = new $_worker();
        if (!($callbackClass instanceof IConsumer)) {
            throw new \Exception("{$_worker} should implements IConsumer");
        }
        $this->_workerDefault = [$callbackClass, 'execute'];
    }
    
    private function _createWorker( $_class ) {
        $callbackClass = new $_class();
        if (!($callbackClass instanceof IConsumer)) {
            throw new \Exception("{$_worker} should implements IConsumer");
        }
        return [$callbackClass, 'execute'];
    }
}