<?php

namespace Sweatshop\Message;

class RoutedMessage extends Message
{
    protected $routeTopic;
    protected $originalMessage;

    public function __construct($topic, Message $message)
    {
        $this->setRouteTopic($topic);
        $this->setOriginalMessage($message);
    }

    public function getRouteTopic()
    {
        return $this->routeTopic;
    }

    public function setRouteTopic($routeTopic)
    {
        $this->routeTopic = $routeTopic;
    }

    /**
     * @return Message
     */
    public function getOriginalMessage()
    {
        return $this->originalMessage;
    }

    public function setOriginalMessage($originalMessage)
    {
        $this->originalMessage = $originalMessage;
    }

    public function getId()
    {
        return $this->getOriginalMessage()->getId();
    }

    public function getOriginalDispatcher()
    {
        return $this->getOriginalMessage()->getOriginalDispatcher();
    }

    public function getParams()
    {
        return $this->getOriginalMessage()->getParams();
    }

    public function getTopic()
    {
        return $this->getOriginalMessage()->getTopic();
    }

    public function setId($id)
    {
        return $this->getOriginalMessage()->setId($id);
    }

    public function setOriginalDispatcher($dispatcher)
    {
        return $this->getOriginalMessage()->setOriginalDispatcher($dispatcher);
    }

    public function setParams($params)
    {
        return $this->getOriginalMessage()->setParams($params);
    }

    public function setTopic($topic)
    {
        return $this->getOriginalMessage()->setTopic($topic);
    }
}
