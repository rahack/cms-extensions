<?php

defined('_JEXEC') or die;

/**
 * Class UpageController
 */
class UpageController extends JControllerLegacy
{
    /**
     * Default display view
     *
     * @param bool $cachable
     * @param bool $urlparams
     *
     * @return mixed
     */
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    /**
     * Send mail to site owner.
     */
    public function sendmail()
    {
        $config = JFactory::getConfig();

        $recipient = $config->get('mailfrom');

        $input = JFactory::getApplication()->input;

        $email = $input->get('email', '', 'string');
        $name = $input->get('name', '');
        $phone = $input->get('phone', '');
        $address = $input->get('address', '');
        $message = $input->get('message', '', 'html');

        $body = <<<STR
Email: $email;		
Name: $name;		
Phone: $phone;
Address: $address;
Message: $message;
STR;


        $mail = JFactory::getMailer();

        $mail->setSubject($name);

        $mail->setBody($body);

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $mail->addRecipient($recipient);
        }

        $ret = $mail->Send();

        header('Content-Type: application/json');
        $data = array();
        if ($ret) {
            $data['success'] = true;
        } else {
            $data['error'] = $ret;
        }
        echo json_encode($data);
        exit;
    }
}
