<?php
/**
 * Mail.php
 *
 * PHP version 8.1+
 *
 * @author Manuel Avelar <me@mavelar.com>
 * @copyright 2024 Manuel Avelar
 * @license http://pixelcreart.com/license license
 * @version 1.0.0
 * @link http://www.pixelcreart.com
 * @package pixelcreart\sendgrid
 */

namespace pixelcreart\sendgrid;

use Exception;
use InvalidArgumentException;
use SendGrid;
use SendGrid\Mail\Mail;
use Yii;
use yii\base\InvalidConfigException;
use yii\mail\BaseMailer;

/**
 * This component allow user to send an email
 *
 * @author Manuel Avelar <me@mavelar.com>
 * @copyright 2024 Manuel Avelar
 * @license http://www.pixelcreart.com/license license
 * @version 1.0.0
 * @link http://www.pixelcreart.com
 * @package pixelcreart\sendgrid
 * @since 1.0.0
 * @todo implement batch messages using API
 */
class Mailer extends BaseMailer
{
    /**
     * @var string Sendgrid API Key
     */
    public $token;

    /**
     * @var array options as defined in https://github.com/sendgrid/sendgrid-php#usage
     */
    public $options;

    /**
     * @inheritdoc
     */
    public $messageClass = 'pixelcreart\sendgrid\Message';
    /**
     * @param Message $message
     * @since 1.0.0
     * @throws InvalidConfigException
     */
    public function sendMessage($message)
    {
        try {
            if (($this->token === null)) {
                throw new InvalidConfigException('Token or login/password are missing');
            }
            $client = null;
            if ($this->token !== null) {
                $client = new SendGrid($this->token, $this->options);
            }
            if ($client === null) {
                throw new InvalidArgumentException('Email transport must be configured');
            }
            $sendGridMail = new Mail();
            $replyTo = $message->getReplyTo();
            if ($replyTo !== null) {
                $sendGridMail->setReplyTo($replyTo);
            }
            $sendGridMail->setFrom($message->getFrom(), $message->getFromName());
            
            foreach($message->getTo() as $email => $name) {
                $sendGridMail->addTo($email, $name);
            }
            foreach($message->getCc() as $email => $name) {
                $sendGridMail->addCc($email, $name);
            }
            foreach($message->getBcc() as $email => $name) {
                $sendGridMail->addBcc($email, $name);
            }
            $sendGridMail->setSubject($message->getSubject());
            foreach($message->getHeaders() as $header) {
                foreach($header as $key => $value) {
                    $sendGridMail->addHeader($key, $value);
                }
            }
            foreach($message->getAttachments() as $attachment) {
                $cid = isset($attachment['ContentID']) ? $attachment['ContentID'] : null;
                $sendGridMail->addAttachment($attachment['File'], $attachment['Name'], $cid);
            }

            $templateId = $message->getTemplateId();

            if ($templateId === null) {
                $data = $message->getHtmlBody();
                if ($data !== null) {
                    $sendGridMail->addContent('text/html',$data);
                }
                $data = $message->getTextBody();
                if ($data !== null) {
                    $sendGridMail->addContent('text/plain',$data);
                }
            } else {
                $sendGridMail->setTemplateId($templateId);
                
                // trigger html template
                $sendGridMail->addContent('text/html',' ');
                // trigger text template
                $sendGridMail->addContent('text/plain',' ');
                $templateModel = $message->getTemplateModel();

                if (empty($templateModel) === false) {
                    $sendGridMail->addDynamicTemplateDatas($templateModel);
                }
            }

            $result = $client->send($sendGridMail);
            /* @var \SendGrid\Response $result */

            return [
                'success' => $result->statusCode()==202,
                'statusCode' => $result->statusCode(),
                'message' => json_decode($result->body()),
                'headers' => $result->headers(),
            ];
        } catch (Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}