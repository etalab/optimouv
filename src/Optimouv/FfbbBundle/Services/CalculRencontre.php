<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/01/2016
 * Time: 17:50
 */

namespace Optimouv\FfbbBundle\Services;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class CalculRencontre implements ConsumerInterface
{
    public	function execute(AMQPMessage $msg)
    {
        echo "Hello $msg->body!".PHP_EOL;
    }
}