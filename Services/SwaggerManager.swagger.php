<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

$_description
	= '<p>
	This is <a href="http://swagger.wordnik.com">Swagger</a>-built documentation detailing the DreamFactory DSP REST API.<br>
	More info can be found <a href="http://www.dreamfactory.com/developers/documentation">here</a>.<br>
	<br>
	<b>Important Notes</b><br>
	Use of our API requires SSL3. If you plan on making requests from Curl, or your Native SDK, make sure you are using version 3.<br>
	For example, in curl: <code>curl -3 url</code><br>
	<br>
	<b>Your API Key</b><br>
	Your “api key” to talk to our API is your App Name as defined in the Administration App.<br>
	For each request, you can :<br>
	<t>1. Append <code>app_name=yourappname</code> to the querystring, or<br>
	<t>2. Send a request header called <b>X-DreamFactory-Application-Name</b> with the value of your app name.<br>
	<br>
	<b>Authentication</b><br>
	If your application is not part of the guest user’s role, or is not being hosted on the DSP,
	then access to any service or data components will require authentication.<br>
	To authenticate a user, simply POST a JSON string to <code>/rest/user/session</code> that takes on the following format:<br>
	<code>\'{“email”:”email_value”, “password”:”password_value”}\'</code><br>
	If successful, in the response, you’ll see a <b>session_id</b> has been created.<br>
	<b>Very Important : </b>
	For all future requests to the API, you’ll need to pass the <b>session_id</b> as a request header called <b>X-DreamFactory-Session-Token</b>.
	</p>';

return array(
	'swaggerVersion' => '1.2',
	'apiVersion'     => API_VERSION,
	'authorizations' => array( "apiKey" => array( "type" => "apiKey", "passAs" => "header" ) ),
	'info'           => array(
		"title"       => "DreamFactory Live API Documentation",
		"description" => $_description,
		//		"termsOfServiceUrl" => "http://www.dreamfactory.com/terms/",
		"contact"     => "support@dreamfactory.com",
		"license"     => "Apache 2.0",
		"licenseUrl"  => "http://www.apache.org/licenses/LICENSE-2.0.html"
	)
);
