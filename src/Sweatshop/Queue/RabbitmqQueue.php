<?php

namespace Sweatshop\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Sweatshop\Message\Message;
use Sweatshop\Worker\Worker;

class RabbitmqQueue extends Queue
{
    private $_conn;
    private $_channel;
    private $_workersQueues = [];
    private $_queues = [];

    public function __construct($sweatshop, $options = [])
    {
        parent::__construct(
            $sweatshop,
            array_merge([
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'polling_delay_max' => 5000000,
                'polling_delay_min' => 100000,
            ], $options)
        );
    }

    public function __destruct()
    {
        if ($this->_conn) {
            $this->getConnection()->close();
        }
        parent::__destruct();
    }

    public function _doPushMessage(Message $message)
    {
        $msg = new AMQPMessage(serialize($message), ['delivery_mode' => 2]);
        $channel = $this->getChannel();
        $channel->basic_publish(
            $msg,
            $this->getExchangeName(),
            $message->getTopic()
        );
    }

    public function _doRegisterWorker($topic, Worker $worker)
    {
        if (empty($this->_workersQueues[$topic])) {
            $this->_workersQueues[$topic] = [];
        }

        array_push($this->_workersQueues[$topic], $worker);
    }

    public function _doRunWorkers()
    {
        foreach ($this->_workersQueues as $topic => $workers) {
            foreach ($workers as $worker) {
                $worker_queue_name = get_class($this).':'.get_class($worker);
                $channel = $this->getChannel();

                try {
                    $channel->queue_declare($worker_queue_name, false, true, false, false);
                    $channel->queue_bind($worker_queue_name, $this->getExchangeName(), $topic);
                } catch (\Exception $e) {
                    echo $e;

                    exit;
                }

                array_push($this->_queues, [
                    'queue' => $worker_queue_name,
                    'worker' => $worker,
                ]);
            }
        }
        $pollingDelay = $this->_options['polling_delay_min'];

        while (!$this->isCandidateForGracefulKill()) {
            foreach ($this->_queues as $q) {
                $queue = $q['queue'];
                $worker = $q['worker'];
                $channel = $this->getChannel();
                $message = $channel->basic_get($queue);

                if ($message) {
                    if (@!$workload = unserialize($message->body)) {
                        $this->getLogger()->info('Sweatshop Error: Unable to process message due to corrupt serialization - '.$message->body.' || '.serialize($message));
                    } elseif ($worker instanceof Worker) {
                        $worker->execute($workload);
                    }
                    // TODO: Log error

                    $channel->basic_ack($message->delivery_info['delivery_tag']);
                    $pollingDelay = max($pollingDelay / 2, $this->_options['polling_delay_min']);
                    $this->workCycleEnd();
                } else {
                    $pollingDelay = min($pollingDelay * 2, $this->_options['polling_delay_max']);
                    usleep($pollingDelay);
                }
            }
        }
    }

    public function _executeWorkerBackground($msg)
    {
        $message = unserialize($msg->body);
    }

    /**
     * @return AMQPSocketConnection;
     */
    private function getConnection()
    {
        // var_dump($this->_options);exit;
        if (!$this->_conn) {
            $this->_conn = new AMQPSocketConnection(
                $this->_options['host'],
                $this->_options['port'],
                $this->_options['user'],
                $this->_options['password']
            );
            // $this->_conn->connect();
            // TODO: check if connection is alive
        }

        return $this->_conn;
    }

    /**
     * @return AMQPChannel
     */
    private function getChannel()
    {
        if (!$this->_channel) {
            $this->_channel = $this->getConnection()->channel();
            $this->declareExchange();
        }

        return $this->_channel;
    }

    private function getExchangeName()
    {
        return 'default';
    }

    private function declareExchange()
    {
        $this->getChannel()->exchange_declare(
            $this->getExchangeName(),
            'direct',
            false,
            true
        );
    }
}
