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

use DreamFactory\Platform\Services\SwaggerManager;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );
$_commonResponses = SwaggerManager::getCommonResponses( array(400, 404, 500) );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'description' => 'Operations available for push notification services.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getResources() - List resources available for the push service.',
                'nickname'         => 'getResources',
                'type'             => 'Resources',
                'event_name'       => '{api_name}.list',
                'responseMessages' => $_commonResponses,
                'notes'            => 'See listed operations for each resource available.',
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listTopics() - List topics available for the push service.',
                'nickname'         => 'listTopics',
                'type'             => 'ComponentList',
                'event_name'       => '{api_name}.list',
                'responseMessages' => $_commonResponses,
                'notes'            => 'See listed operations for each resource available.',
                'parameters'       => array(
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the topics in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/{topic_name}',
        'description' => 'Operations for push topics.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getTopicAttributes() - Retrieve topic definition for the given topic.',
                'nickname'         => 'getTopicAttributes',
                'event_name'       => array('{api_name}.{topic_name}.describe', '{api_name}.topic_described'),
                'type'             => 'GetTopicResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This describes the topic, detailing its available properties.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'publishSimpleMessage() - Send a message to the given topic.',
                'nickname'         => 'publishSimpleMessage',
                'type'             => 'PublishResponse',
                'event_name'       => array(
                    '{api_name}.{topic_name}.publish',
                    '{api_name}.topics.publish'
                ),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic publish parameters.',
                        'allowMultiple' => false,
                        'type'          => 'PublishSimpleRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of topic publish properties.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'publishMessage() - Send a message to the given topic.',
                'nickname'         => 'publishMessage',
                'type'             => 'PublishResponse',
                'event_name'       => array(
                    '{api_name}.{topic_name}.publish',
                    '{api_name}.topics.publish'
                ),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic message parameters.',
                        'allowMultiple' => false,
                        'type'          => 'PublishRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of topic publish properties.',
            ),
        ),
    ),
);

$_models = array(
    'GetTopicResponse'          => array(
        'id'         => 'GetTopicResponse',
        'properties' => array(
            'Attributes'       => array(
                'type'        => 'TopicAttributes',
                'description' => 'Identifier/Name for the topic.',
            ),
            'ResponseMetadata' => array(
                'type'        => 'ResponseMetadata',
                'description' => 'Displayable singular name for the topic.',
            ),
        ),
    ),
    'TopicAttributes'       => array(
        'id'         => 'TopicAttributes',
        'properties' => array(
            'TopicArn'                => array(
                'type'        => 'string',
                'description' => 'The topic\'s Amazon Resource Name.',
            ),
            'Owner'                   => array(
                'type'        => 'string',
                'description' => 'The AWS account ID of the topic\'s owner.',
            ),
            'Policy'                  => array(
                'type'        => 'string',
                'description' => 'The JSON serialization of the topic\'s access control policy.',
            ),
            'DisplayName'             => array(
                'type'        => 'string',
                'description' => 'The human-readable name used in the "From" field for notifications to email and email-json endpoints.',
            ),
            'SubscriptionsPending'    => array(
                'type'        => 'string',
                'description' => 'The number of subscriptions pending confirmation on this topic.',
            ),
            'SubscriptionsConfirmed'  => array(
                'type'        => 'string',
                'description' => 'The number of confirmed subscriptions on this topic.',
            ),
            'SubscriptionsDeleted'    => array(
                'type'        => 'string',
                'description' => 'The number of deleted subscriptions on this topic.',
            ),
            'DeliveryPolicy'          => array(
                'type'        => 'string',
                'description' => 'The JSON serialization of the topic\'s delivery policy.',
            ),
            'EffectiveDeliveryPolicy' => array(
                'type'        => 'string',
                'description' => 'The JSON serialization of the effective delivery policy that takes into account system defaults.',
            ),
        ),
    ),
    'PublishSimpleRequest'   => array(
        'id'         => 'PublishSimpleRequest',
        'properties' => array(
            'TopicArn'          => array(
                'type'        => 'string',
                'description' => 'The topic you want to publish to.',
            ),
            'TargetArn'         => array(
                'type'        => 'string',
                'description' => 'Either TopicArn or EndpointArn, but not both.',
            ),
            'Message'           => array(
                'type'        => 'string',
                'description' => 'The message you want to send to the topic, sends the same message to all transport protocols. If you want to send different messages for each transport protocol, set the value of the MessageStructure parameter to json and use a JSON object for the Message parameter.',
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageStructure'  => array(
                'type'        => 'string',
                'description' => 'Set MessageStructure to "json" if you want to send a different message for each protocol.',
            ),
            'MessageAttributes' => array(
                'type'        => 'Array',
                'description' => 'An array of key-value pairs containing user-specified message attributes.',
                'items'       => array(
                    '$ref' => 'TopicMessageAttribute',
                ),
            ),
        ),
    ),
    'PublishRequest'   => array(
        'id'         => 'PublishRequest',
        'properties' => array(
            'TopicArn'          => array(
                'type'        => 'string',
                'description' => 'The topic you want to publish to.',
            ),
            'TargetArn'         => array(
                'type'        => 'string',
                'description' => 'Either TopicArn or EndpointArn, but not both.',
            ),
            'Message'           => array(
                'type'        => 'TopicMessage',
                'description' => 'The message you want to send to the topic. The \'default\' field is required.',
                'required' => true,
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageStructure'  => array(
                'type'        => 'string',
                'description' => 'Set MessageStructure to "json".',
                'default' => 'json',
            ),
            'MessageAttributes' => array(
                'type'        => 'Array',
                'description' => 'An array of key-value pairs containing user-specified message attributes.',
                'items'       => array(
                    '$ref' => 'MessageAttribute',
                ),
            ),
        ),
    ),
    'TopicMessage'          => array(
        'id'         => 'TopicMessage',
        'properties' => array(
            'default' => array(
                'type'        => 'string',
                'description' => 'Amazon SNS supports the following logical data types: String, Number, and Binary.',
                'required' => true,
            ),
            'email'   => array(
                'type'        => 'string',
                'description' => 'Strings are Unicode with UTF8 binary encoding.',
            ),
            'sqs'     => array(
                'type'        => 'string',
                'description' => 'Binary type attributes can store any binary data, for example, compressed data, encrypted data, or images.',
            ),
            'http'    => array(
                'type'        => 'string',
                'description' => 'Binary type attributes can store any binary data, for example, compressed data, encrypted data, or images.',
            ),
            'https'   => array(
                'type'        => 'string',
                'description' => 'Binary type attributes can store any binary data, for example, compressed data, encrypted data, or images.',
            ),
            'sms'     => array(
                'type'        => 'string',
                'description' => 'Binary type attributes can store any binary data, for example, compressed data, encrypted data, or images.',
            ),
            'APNS'    => array(
                'type'        => 'string',
                'description' => '{\"aps\":{\"alert\": \"ENTER YOUR MESSAGE\",\"sound\":\"default\"} }',
            ),
            'GCM'     => array(
                'type'        => 'string',
                'description' => '{ \"data\": { \"message\": \"ENTER YOUR MESSAGE\" } }',
            ),
            'ADM'     => array(
                'type'        => 'string',
                'description' => '{ \"data\": { \"message\": \"ENTER YOUR MESSAGE\" } }',
            ),
            'BAIDU'   => array(
                'type'        => 'string',
                'description' => '{\"title\":\"ENTER YOUR TITLE\",\"description\":\"ENTER YOUR DESCRIPTION\"}',
            ),
            'MPNS'    => array(
                'type'        => 'string',
                'description' => '<?xml version=\"1.0\" encoding=\"utf-8\"?><wp:Notification xmlns:wp=\"WPNotification\"><wp:Tile><wp:Count>ENTER COUNT</wp:Count><wp:Title>ENTER YOUR MESSAGE</wp:Title></wp:Tile></wp:Notification>',
            ),
            'WNS'     => array(
                'type'        => 'string',
                'description' => '<badge version=\"1\" value=\"23\"/>',
            ),
        ),
    ),
    'MessageAttribute' => array(
        'id'         => 'MessageAttribute',
        'properties' => array(
            'DataType'    => array(
                'type'        => 'string',
                'description' => 'Amazon SNS supports the following logical data types: String, Number, and Binary.',
                'required' => true,
            ),
            'StringValue' => array(
                'type'        => 'string',
                'description' => 'Strings are Unicode with UTF8 binary encoding.',
            ),
            'BinaryValue' => array(
                'type'        => 'string',
                'description' => 'Binary type attributes can store any binary data, for example, compressed data, encrypted data, or images.',
            ),
        ),
    ),
    'PublishResponse'  => array(
        'id'         => 'PublishResponse',
        'properties' => array(
            'MessageId'        => array(
                'type'        => 'string',
                'description' => 'Unique identifier assigned to the published message.',
            ),
            'ResponseMetadata' => array(
                'type'        => 'ResponseMetadata',
                'description' => 'Metadata for the response.',
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models );

unset( $_commonResponses, $_models );

return $_base;