<?php

namespace shinomontaz\yii2-rabbit\interfaces;

interface IConsumer {
  public function execute( \AMQPEnvelope $msg );
}