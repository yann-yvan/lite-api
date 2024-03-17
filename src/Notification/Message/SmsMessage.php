<?php

namespace Nycorp\LiteApi\Notification\Message;

class SmsMessage
{
    private string $_recipient;

    private string $_content;

    public function getRecipient(): string
    {
        return $this->_recipient;
    }

    /**
     * @return $this
     */
    public function setRecipient(string $recipient): SmsMessage
    {
        $this->_recipient = $recipient;

        return $this;
    }

    public function getContent(): string
    {
        return $this->_content;
    }

    /**
     * @return $this
     */
    public function setContent(string $content): SmsMessage
    {
        $this->_content = $content;

        return $this;
    }
}
