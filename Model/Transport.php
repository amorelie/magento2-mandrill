<?php
/**
 * Ebizmarts_Mandrill Magento JS component
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_Mandrill
 *
 * @author      Ebizmarts Team <info@ebizmarts.com>
 * @copyright   Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ebizmarts\Mandrill\Model;

use Ebizmarts\Mandrill\Helper\Data;
use Ebizmarts\Mandrill\Model\Api\Mandrill;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\MimeMessageInterface;
use Magento\Framework\Mail\TransportInterface;
use Laminas\Mime\Mime;

class Transport implements TransportInterface
{
    /**
     * @var EmailMessageInterface
     */
    private $message;

    /**
     * @var Mandrill
     */
    private $api;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @param EmailMessageInterface $message
     * @param Mandrill $api
     * @param Data $helper
     */
    public function __construct(
        EmailMessageInterface $message,
        Mandrill $api,
        Data $helper
    ) {
        $this->message  = $message;
        $this->api      = $api;
        $this->helper   = $helper;
    }

    public function sendMessage(): void
    {
        try {
            $mandrillApiInstance = $this->getMandrillApiInstance();

            if ($mandrillApiInstance === null) {
                return;
            }
            /** @var \Magento\Framework\Mail\Address $fromAddress */
            $fromAddress = $this->message->getFrom() ? \current($this->message->getFrom()) : null;
            $message = [
                'subject' => $this->message->getSubject(),
                'from_name' => $fromAddress ? $fromAddress->getName() : '',
                'from_email' => $fromAddress ? $fromAddress->getEmail() : '',
            ];

            foreach ($this->message->getTo() as $to) {
                $message['to'][] = [
                    'email' => $to->getEmail(),
                    'name' => $to->getName(),
                ];
            }

            foreach ($this->message->getBcc() as $bcc) {
                $message['to'][] = [
                    'email' => $bcc->getEmail(),
                    'name' => $bcc->getName(),
                    'type' => 'bcc',
                ];
            }

            if ($headers = $this->message->getHeaders()) {
                $message['headers'] = $headers;
            }

            $message = $this->prepareBody($message, $this->message->getMessageBody());

            $result = $mandrillApiInstance->messages->send($message);
            $this->processApiCallResult($result);
        } catch (\Exception $e) {
            $this->helper->log($e->getMessage());
        }
    }

    /**
     * @param array $message
     * @param MimeMessageInterface $body
     *
     * @throws \Exception
     *
     * @return array
     */
    private function prepareBody(array $message, MimeMessageInterface $body): array
    {
        $attachments = [];
        $content = '';

        if ($body->isMultiPart()) {
            $parts = $body->getParts();

            foreach ($parts as $part) {
                if ($part->getDisposition() === Mime::DISPOSITION_ATTACHMENT) {
                    $attachments[] = [
                        'type' => $part->getType(),
                        'name' => $part->getFileName(),
                        'content'=> $part->getContent(),
                    ];
                } else {
                    $content .= $part->getRawContent();
                }
            }
        } else {
            $part = \current($body->getParts());
            $content = $part->getRawContent();
        }

        if (empty($content)) {
            throw new \Exception('Empty body');
        }
        $message['html'] = $content;

        if ($attachments) {
            $message['attachments'] = $attachments;
        }

        return $message;
    }

    /**
     * @param $result
     * @throws \Magento\Framework\Exception\MailException
     */
    private function processApiCallResult($result): void
    {
        $currentResult = current($result);

        if (array_key_exists('status', $currentResult) && $currentResult['status'] == 'rejected') {
            throw new \Magento\Framework\Exception\MailException(
                new \Magento\Framework\Phrase('Email sending failed: %1', [$currentResult['reject_reason']])
            );
        }
    }

    /**
     * @return \Mandrill
     */
    private function getMandrillApiInstance()
    {
        return $this->api->getApi();
    }

    /**
     * Get message
     *
     * @return EmailMessageInterface
     *
     * @since 100.2.0
     */
    public function getMessage()
    {
        return $this->message;
    }
}
