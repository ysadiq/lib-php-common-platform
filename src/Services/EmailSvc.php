<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Utility\EmailUtilities;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\EmailTemplate;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * EmailSvc
 * A service to handle email services accessed through the REST API.
 *
 */
class EmailSvc extends BasePlatformRestService
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var null|\Swift_SmtpTransport|\Swift_SendMailTransport|\Swift_MailTransport
	 */
	protected $_transport = null;
	/**
	 * @var null|array
	 */
	protected $_parameters = array();

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Create a new EmailSvc
	 *
	 * @param array $config
	 */
	public function __construct( $config )
	{
		parent::__construct( $config );

		$_transportType = Option::get( $config, 'storage_type' );
		$_credentials = Option::get( $config, 'credentials', array() );
		// Create the Transport
		$this->_transport = EmailUtilities::createTransport( $_transportType, $_credentials );

		$this->_parameters = Option::get( $config, 'parameters', array() );
	}

	/**
	 * @return array
	 * @throws BadRequestException
	 */
	protected function _handleGet()
	{

		// no resources currently
		return array();
	}

	/**
	 * @return array
	 * @throws BadRequestException
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostedData( false, true );
		Option::sins( $_data, 'template', FilterInput::request( 'template' ) );
		Option::sins( $_data, 'template_id', FilterInput::request( 'template_id' ) );

		$_count = $this->sendEmail( $_data );

		return array( 'count' => $_count );
	}

	/**
	 * @param $name
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 *
	 * @return array
	 */
	public static function getTemplateDataByName( $name )
	{
		// find template in system db
		$_template = EmailTemplate::model()->findByAttributes( array( 'name' => $name ) );
		if ( empty( $_template ) )
		{
			throw new NotFoundException( "Email Template '$name' not found" );
		}

		return $_template->getAttributes();
	}

	/**
	 * @param $id
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 *
	 * @return array
	 */
	public static function getTemplateDataById( $id )
	{
		// find template in system db
		$_template = EmailTemplate::model()->findByPk( $id );
		if ( empty( $_template ) )
		{
			throw new NotFoundException( "Email Template id '$id' not found" );
		}

		return $_template->getAttributes();
	}

	/**
	 * Default Send Email function determines specific mailing function
	 * based on service configuration.
	 *
	 * @param array $data Array of email parameters
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return int
	 */
	public function sendEmail( $data = array() )
	{
		// build email from posted data
		$_template = Option::get( $data, 'template', null, true );
		$_templateId = Option::get( $data, 'template_id', null, true );
		$_templateData = array();
		if ( !empty( $_template ) )
		{
			$_templateData = static::getTemplateDataByName( $_template );
		}
		elseif ( !empty( $_templateId ) )
		{
			$_templateData = static::getTemplateDataById( $_templateId );
		}

		if ( empty( $_templateData ) && empty( $data ) )
		{
			throw new BadRequestException( 'No valid data in request.' );
		}

		// build email from config defaults and template defaults overwritten by posted data
		$data = array_merge( Option::get( $_templateData, 'defaults', array(), true ), $data );
		$data = array_merge( $this->_parameters, $_templateData, $data );

		/*
		 * @var string $_to   comma-delimited list of receiver addresses
		 * @var string $_cc   comma-delimited list of CC'd addresses
		 * @var string $_bcc  comma-delimited list of BCC'd addresses
		 * @var string $_subject     Text only subject line
		 * @var string $_text   Text only version of the email body
		 * @var string $_html   Escaped HTML version of the email body
		 * @var string $_fromName   Name displayed for the sender
		 * @var string $_fromEmail  Email displayed for the sender
		 * @var string $_replyName  Name displayed for the reply to
		 * @var string $_replyEmail Email used for the sender reply to
		 * @var array  $data        Name-Value pairs for replaceable data in subject and body
		 */
		$_to = Option::get( $data, 'to' );
		$_cc = Option::get( $data, 'cc' );
		$_bcc = Option::get( $data, 'bcc' );
		$_subject = Option::get( $data, 'subject' );
		$_text = Option::get( $data, 'body_text' );
		$_html = Option::get( $data, 'body_html' );
		$_fromName = Option::get( $data, 'from_name' );
		$_fromEmail = Option::get( $data, 'from_email' );
		$_replyName = Option::get( $data, 'reply_to_name' );
		$_replyEmail = Option::get( $data, 'reply_to_email' );

		if ( empty( $_fromEmail ) )
		{
			$_fromEmail = 'no-reply@dreamfactory.com';
			$data['from_email'] = $_fromEmail;
			if ( empty( $_fromName ) )
			{
				$_fromName = 'DreamFactory Software, Inc.';
				$data['from_name'] = $_fromName;
			}
		}

		$_to = EmailUtilities::sanitizeAndValidateEmails( $_to, 'swift' );
		if ( !empty( $_cc ) )
		{
			$_cc = EmailUtilities::sanitizeAndValidateEmails( $_cc, 'swift' );
		}
		if ( !empty( $_bcc ) )
		{
			$_bcc = EmailUtilities::sanitizeAndValidateEmails( $_bcc, 'swift' );
		}

		$_fromEmail = EmailUtilities::sanitizeAndValidateEmails( $_fromEmail, 'swift' );
		if ( !empty( $_replyEmail ) )
		{
			$_replyEmail = EmailUtilities::sanitizeAndValidateEmails( $_replyEmail, 'swift' );
		}

		// handle special case, add server side details options
		$_hostUrl = Curl::currentUrl( false, false );
		Option::sins( $data, 'dsp.host_url', $_hostUrl );
		Option::sins( $data, 'dsp.confirm_invite_url', $_hostUrl . Pii::getParam( 'dsp.confirm_invite_url' ) );
		Option::sins( $data, 'dsp.confirm_register_url', $_hostUrl . Pii::getParam( 'dsp.confirm_register_url' ) );
		Option::sins( $data, 'dsp.confirm_reset_url', $_hostUrl . Pii::getParam( 'dsp.confirm_reset_url' ) );
		Option::sins( $data, 'dsp.name', Pii::getParam( 'dsp_name' ) );

		// do placeholder replacement, currently {xxx}
		if ( !empty( $data ) )
		{
			foreach ( $data as $name => $value )
			{
				if ( is_string( $value ) )
				{
					// replace {xxx} in subject
					$_subject = str_replace( '{' . $name . '}', $value, $_subject );
					// replace {xxx} in body - text and html
					$_text = str_replace( '{' . $name . '}', $value, $_text );
					$_html = str_replace( '{' . $name . '}', $value, $_html );
				}
			}
		}

		if ( empty( $_html ) )
		{
			// get some kind of html
			$_html = str_replace( "\r\n", "<br />", $_text );
		}

		$_message = EmailUtilities::createMessage(
			$_to,
			$_cc,
			$_bcc,
			$_subject,
			$_text,
			$_html,
			$_fromName,
			$_fromEmail,
			$_replyName,
			$_replyEmail
		);

		return EmailUtilities::sendMessage( $this->_transport, $_message );
	}

	/*
		public static function sendUserEmail( $template, $data )
		{
			$to = Option::get( $data, 'to' );
			$cc = Option::get( $data, 'cc' );
			$bcc = Option::get( $data, 'bcc' );
			$subject = Option::get( $template, 'subject' );
			$content = Option::get( $template, 'content' );
			$bodyText = Option::get( $content, 'text' );
			$bodyHtml = Option::get( $content, 'html' );
			try
			{
				$svc = ServiceHandler::getServiceObject( 'email' );
				$result = ( $svc ) ? $svc->sendEmail( $to, $cc, $bcc, $subject, $bodyText, $bodyHtml ) : false;
				if ( !filter_var( $result, FILTER_VALIDATE_BOOLEAN ) )
				{
					$msg = "Error: Failed to send user email.";
					if ( is_string( $result ) )
					{
						$msg .= "\n$result";
					}
					throw new Exception( $msg );
				}
			}
			catch ( Exception $ex )
			{
				throw $ex;
			}
		}

		protected function sendUserWelcomeEmail( $email, $fullname )
		{
	//        $to = "$fullname <$email>";
			$to = $email;
			$subject = 'Welcome to ' . $this->siteName;
			$body = 'Hello ' . $fullname . ",\r\n\r\n" .
					"Welcome! Your registration  with " . $this->siteName . " is complete.\r\n" .
					"\r\n" .
					"Regards,\r\n" .
					"Webmaster\r\n" .
					$this->siteName;

			$html = str_replace( "\r\n", "<br />", $body );
		}

		protected function sendAdminIntimationOnRegComplete( $email, $fullname )
		{
			$subject = "Registration Completed: " . $fullname;
			$body = "A new user registered at " . $this->siteName . ".\r\n" .
					"Name: " . $fullname . "\r\n" .
					"Email address: " . $email . "\r\n";

			$html = str_replace( "\r\n", "<br />", $body );
		}

		protected function sendResetPasswordLink( $email, $full_name )
		{
	//        $to = "$full_name <$email>";
			$to = $email;
			$subject = "Your reset password request at " . $this->siteName;
			$link = Pii::app()->getHomeUrl() . '?code=' . urlencode( $this->getResetPasswordCode( $email ) );

			$body = "Hello " . $full_name . ",\r\n\r\n" .
					"There was a request to reset your password at " . $this->siteName . "\r\n" .
					"Please click the link below to complete the request: \r\n" . $link . "\r\n" .
					"Regards,\r\n" .
					"Webmaster\r\n" .
					$this->siteName;

			$html = str_replace( "\r\n", "<br />", $body );
		}

		protected function sendNewPassword( $user_rec, $new_password )
		{
			$email = $user_rec['email'];
	//        $to = $user_rec['name'] . ' <' . $email . '>';
			$to = $email;
			$subject = "Your new password for " . $this->siteName;
			$body = "Hello " . $user_rec['name'] . ",\r\n\r\n" .
					"Your password was reset successfully. " .
					"Here is your updated login:\r\n" .
					"email:" . $user_rec['email'] . "\r\n" .
					"password:$new_password\r\n" .
					"\r\n" .
					"Login here: " . Utilities::getAbsoluteURLFolder() . "login.php\r\n" .
					"\r\n" .
					"Regards,\r\n" .
					"Webmaster\r\n" .
					$this->siteName;

			$html = str_replace( "\r\n", "<br />", $body );
		}

		protected function sendUserConfirmationEmail( $email, $confirmcode, $fullname )
		{
			$confirm_url = Utilities::getAbsoluteURLFolder() . 'confirmreg.php?code=' . $confirmcode;

	//        $to = "$fullname <$email>";
			$to = $email;
			$subject = "Your registration with " . $this->siteName;
			$body = "Hello " . $fullname . ",\r\n\r\n" .
					"Thanks for your registration with " . $this->siteName . "\r\n" .
					"Please click the link below to confirm your registration.\r\n" .
					"$confirm_url\r\n" .
					"\r\n" .
					"Regards,\r\n" .
					"Webmaster\r\n" .
					$this->siteName;

			$html = str_replace( "\r\n", "<br />", $body );
		}

		protected function sendAdminIntimationEmail( $email, $fullname )
		{
			$subject = "New registration: " . $fullname;
			$body = "A new user registered at " . $this->siteName . ".\r\n" .
					"Name: " . $fullname . "\r\n" .
					"Email address: " . $email;

			$html = str_replace( "\r\n", "<br />", $body );
		}

	*/
}
