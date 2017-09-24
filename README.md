# yii2-rabbit

confic example:
```php
'components' => [
        ...
'rabbit' => [
    'class' => 'shinomontaz\rabbit\Rabbit',
    'host' => '<host>',
    'port' => '<port>',
    'user' => '<user>',
    'password' => '<pass>',
    'vhost' => '<vhost>',
    'layout' => [
    '<exchangeName1>' => [
        'type' => '<direct>',
        'durable' => <durable>,
        'queues' => [
            '<queueName1>' => [
                'durable' => <durable>,
                'type' => '<direct>',
                'worker' => path\to\worker::class,
            ],
        ],
    ]
    ],
    ],
    ...
],
```

```php
\Yii::$app->rabbit->schedule( $message, '<exchangeName>', 'routingKey');
```

```php
php yii consoleController/process <queueName>
```
