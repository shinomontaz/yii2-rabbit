# yii2-rabbit

confic example:
components => [
        ...
        'rabbit' => [
  				'class' => 'app\components\Rabbit',
          'host' => '127.0.0.1',
          'port' => '5672',
          'user' => 'guest',
          'password' => 'guest',
          'durable' => true,
          'exchange'  => [
                'name' => 'ceb',
                'type' => 'direct'
            ],
          'vhost' => '/',
          'worker' => path\to\worker::class,
        ],
        ...
],
        
