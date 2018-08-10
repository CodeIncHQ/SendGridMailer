<?php
//
// +---------------------------------------------------------------------+
// | CODE INC. SOURCE CODE                                               |
// +---------------------------------------------------------------------+
// | Copyright (c) 2018 - Code Inc. SAS - All Rights Reserved.           |
// | Visit https://www.codeinc.fr for more information about licensing.  |
// +---------------------------------------------------------------------+
// | NOTICE:  All information contained herein is, and remains the       |
// | property of Code Inc. SAS. The intellectual and technical concepts  |
// | contained herein are proprietary to Code Inc. SAS are protected by  |
// | trade secret or copyright law. Dissemination of this information or |
// | reproduction of this material is strictly forbidden unless prior    |
// | written permission is obtained from Code Inc. SAS.                  |
// +---------------------------------------------------------------------+
//
// Author:   Joan Fabrégat <joan@codeinc.fr>
// Date:     2018-03-30
// Time:     12:00
// Project:  SendGridMailer
//
namespace CodeInc\SendGridMailer;
use CodeInc\Mailer\Interfaces\EmailInterface;
use CodeInc\Mailer\Interfaces\MailerInterface;
use SendGrid\Mail\Content;
use SendGrid\Mail\From;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\To;


/**
 * Class SendGridMail
 *
 * @see https://github.com/sendgrid/sendgrid-php
 * @see https://packagist.org/packages/sendgrid/sendgrid
 * @package CodeInc\SendGridMailer
 */
class SendGridMailer implements MailerInterface
{
	/**
	 * @var \SendGrid
	 */
	private $sendGridClient;

    /**
     * SendGridMailer constructor.
     *
     * @param \SendGrid $sendGridClient
     */
	public function __construct(\SendGrid $sendGridClient)
    {
        $this->sendGridClient = $sendGridClient;
    }

    /**
     * @param EmailInterface $email
     * @return Mail
     */
	private function buildMail(EmailInterface $email):Mail
    {
		// Configure l'email SendGrid
        $mail = new Mail();
		$mail->setFrom(new From($email->getSender()->getAddress(), $email->getSender()->getName()));
		$mail->setSubject($email->getSubject());
		if ($htmlBody = $email->getHtmlBody()) {
            $mail->addContent(new Content("text/html", $htmlBody));
        }
        if ($textBody = $email->getTextBody()) {
            $mail->addContent(new Content("text/plain", $textBody));
        }
		$personalization = new Personalization();
        foreach ($email->getRecipients() as $recipient) {
            $personalization->addTo(new To($recipient->getAddress(), $recipient->getName()));
        }
		$mail->addPersonalization($personalization);
		
		return $mail;
	}

    /**
     * @inheritdoc
     * @param EmailInterface $email
     */
	public function send(EmailInterface $email):void
    {
        $mail = $this->buildMail($email);
        $response = $this->sendGridClient->client->mail()->send()->post($mail);

        if ($response->statusCode() >= 400) {
            // Lecture du ou des messages d'erreur
            if (($responseBody = json_decode($response->body(), true)) !== null &&
                isset($responseBody['errors'])
                && is_array($responseBody['errors'])
                && !empty($responseBody['errors'])
            ) {

                $errors = [];
                foreach ($responseBody['errors'] as $entry) {
                    if (isset($entry['message'])) {
                        $errors[] = $entry['message'].(isset($entry['field']) ? ' (field: '.$entry['field'].')' : '');
                    }
                }
                throw new \RuntimeException(
                    sprintf("Error while sending the email via the SendGrid API: %s",
                        implode(", ", $errors))
                );
            }

            // Si pas de message d'erreur dans la réponse de SendGrid
            else {
                throw new \RuntimeException(
                    sprintf("A SendGrid API error number %s was encountered while sending the email "
                        ."(see: https://sendgrid.com/docs/API_Reference/Web_API_v3/Mail/errors.html)",
                        $response->statusCode())
                );
            }
        }
	}
}