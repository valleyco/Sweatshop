<?php

namespace Sweatshop\Queue;

use Sweatshop\Message\Message;
use Sweatshop\Worker\Worker;

class InternalQueue extends Queue
{
    protected $_workers = [];

    protected function _doPushMessage(Message $message)
    {
        $topic = $message->getTopic();
        $results = [];
        if (!empty($this->_workers[$topic])) {
            foreach ($this->_workers[$topic] as $worker) {
                $results[] = $worker->pushMessage($message);
            }
        }

        return $results;
    }

    protected function _doRegisterWorker($topic, Worker $worker)
    {
        if (empty($this->_workers[$topic])) {
            $this->_workers[$topic] = [];
        }
        $this->_workers[$topic][] = $worker;

        return true;
    }

    protected function _doRunWorkers()
    {
    }
}
