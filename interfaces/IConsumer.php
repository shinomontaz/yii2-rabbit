<?php

namespace shinomontaz\rabbit\interfaces;

interface IConsumer {
  public function execute( \AMQPEnvelope $msg );
}