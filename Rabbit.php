<?php

namespace shinomontaz\rabbit;

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

    private function _getConnection() {
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

    private function _getChannel() {
        if( !$this->_channel ) {
            $this->_channel    = new \AMQPChannel($this->_getConnection());
        }
        return $this->_channel;
    }

    private function _getQueue( $queueName ) {
        return $this->_queues[$queueName];
    }

    public function schedule($message, $exchange, $key)
    {
        if( !$this->_getExchange( $exchange )->publish( (string)$message, $key ) ) {
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
      while ($i++ <= $this->_chunkSize && $message = $this->_getQueue( $queueName )->get(\AMQP_AUTOACK)) {
        call_user_func($worker, $message);
      }
      if( $i == 1 ) {
        sleep(10);
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
    
    public function setLayout( $_layout ) {
      foreach( $_layout as $exchangeName => $info ) {
        $this->_exchanges[$exchangeName] = new \AMQPExchange( $this->_getChannel() );
        $this->_exchanges[$exchangeName]->setName( $exchangeName );
        $this->_exchanges[$exchangeName]->setType( $info['type'] );
        if( !isset( $info['durable'] ) || $info['durable'] ) {
            $this->_exchanges[$exchangeName]->setFlags(\AMQP_DURABLE);
        }
        $this->_exchanges[$exchangeName]->declareExchange();
        
        foreach( $info['queues'] as $queueName => $queueData ) {
          $queue = new \AMQPQueue( $this->_getChannel() );
          $queue->setName( $queueName );
          if( !isset( $queueData['durable'] ) || $queueData['durable'] ) {
              $queue->setFlags(\AMQP_DURABLE);
          }
          $queue->declareQueue();
          $queue->bind( $exchangeName, $queueName );
          $this->_queues[$queueName] = $queue;
          if( isset( $queueData['worker'] ) ) {
            $this->_workers[ $queueName ] = $this->_createWorker( $queueData['worker'] );
          }
        }
      }
    }

    public function setWorker( $_worker ) {
        $this->_workerDefault = $this->_createWorker( $_worker );
    }
    
    private function _createWorker( $_class ) {
        $callbackClass = new $_class();
        if ( !($callbackClass instanceof interfaces\IConsumer) ) {
            throw new \Exception("{$_class} should implements IConsumer");
        }
        return [$callbackClass, 'execute'];
    }
}