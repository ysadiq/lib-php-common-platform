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
                'notes'            => 'See listed operations for each resource available.',
                'type'             => 'Resources',
                'event_name'       => array('{api_name}.list'),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listResources() - List resources available for the push service.',
                'nickname'         => 'listResources',
                'notes'            => 'See listed operations for each resource available.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the resources in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'getAccessComponents() - List all role accessible components.',
                'nickname'         => 'getAccessComponents',
                'notes'            => 'List the names of all the role accessible components.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'as_access_components',
                        'description'   => 'Return the names of all the accessible components.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'simplePublish() - Send a simple message to a topic or endpoint.',
                'nickname'         => 'simplePublish',
                'notes'            => 'Post data should be an array of topic publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.publish'),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic publish parameters.',
                        'allowMultiple' => false,
                        'type'          => 'SimplePublishRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'publish() - Send a message to a topic or endpoint.',
                'nickname'         => 'publish',
                'notes'            => 'Post data should be an array of topic publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.publish'),
                'parameters'       => array(
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
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/topic',
        'description' => 'Operations for push topics.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getTopics() - Retrieve all topics available for the push service.',
                'nickname'         => 'getTopics',
                'notes'            => 'This returns the topics as resources.',
                'event_name'       => array('{api_name}.topic.list'),
                'type'             => 'GetTopicsResponse',
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listTopics() - List topics available for the push service.',
                'nickname'         => 'listTopics',
                'notes'            => 'Returns only the names of the topics in an array.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.topic.list'),
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
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createTopic() - Create a topic.',
                'nickname'         => 'createTopic',
                'notes'            => 'Post data should be an array of topic attributes including \'Name\'.',
                'type'             => 'TopicIdentifier',
                'event_name'       => array('{api_name}.topic.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic attributes.',
                        'allowMultiple' => false,
                        'type'          => 'TopicRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/topic/{topic_name}',
        'description' => 'Operations for a specific push topic.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getTopicAttributes() - Retrieve topic definition for the given topic.',
                'nickname'         => 'getTopicAttributes',
                'notes'            => 'This retrieves the topic, detailing its available properties.',
                'event_name'       => array('{api_name}.topic.{topic_name}.retrieve', '{api_name}.topic_retrieved'),
                'type'             => 'TopicAttributesResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'simplePublishTopic() - Send a message to the given topic.',
                'nickname'         => 'simplePublishTopic',
                'notes'            => 'Post data should be an array of topic publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.topic.{topic_name}.publish', '{api_name}.topic_published'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic publish parameters.',
                        'allowMultiple' => false,
                        'type'          => 'SimplePublishTopicRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'publishTopic() - Send a message to the given topic.',
                'nickname'         => 'publishTopic',
                'notes'            => 'Post data should be an array of topic publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.topic.{topic_name}.publish', '{api_name}.topic_published'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic message parameters.',
                        'allowMultiple' => false,
                        'type'          => 'PublishTopicRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'setTopicAttributes() - Update a given topic\'s attributes.',
                'nickname'         => 'setTopicAttributes',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.topic.{topic_name}.update', '{api_name}.topic_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic attributes.',
                        'allowMultiple' => false,
                        'type'          => 'TopicAttributesRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of topic attributes including \'Name\'.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteTopic() - Delete a given topic.',
                'nickname'         => 'deleteTopic',
                'notes'            => '',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.topic.{topic_name}.delete', '{api_name}.topic_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/topic/{topic_name}/subscription',
        'description' => 'Operations for push subscriptions.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getSubscriptionsByTopic() - Retrieve subscriptions for the given topic.',
                'nickname'         => 'getSubscriptionsByTopic',
                'notes'            => 'This return the subscriptions as resources.',
                'event_name'       => array('{api_name}.topic.{topic_name}.subscription.list'),
                'type'             => 'GetSubscriptionsResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listSubscriptionsByTopic() - List subscriptions available for the given topic.',
                'nickname'         => 'listSubscriptionsByTopic',
                'notes'            => 'Return only the names of the subscriptions in an array.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.topic.{topic_name}.subscription.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the subscriptions in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'subscribeTopic() - Create a subscription for the given topic.',
                'nickname'         => 'subscribeTopic',
                'type'             => 'SubscriptionIdentifier',
                'event_name'       => array('{api_name}.topic.{topic_name}.subscription.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'topic_name',
                        'description'   => 'Full ARN or simplified name of the topic to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of subscription attributes.',
                        'allowMultiple' => false,
                        'type'          => 'SubscriptionTopicRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of subscription attributes including \'Name\'.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/subscription',
        'description' => 'Operations for push subscriptions.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getSubscriptions() - Retrieve all subscriptions as resources.',
                'nickname'         => 'getSubscriptions',
                'notes'            => 'This describes the topic, detailing its available properties.',
                'event_name'       => array('{api_name}.subscription.list'),
                'type'             => 'GetSubscriptionsResponse',
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listSubscriptions() - List subscriptions available for the push service.',
                'nickname'         => 'listSubscriptions',
                'notes'            => 'See listed operations for each subscription available.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.subscription.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the subscriptions in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'subscribe() - Create a subscription.',
                'nickname'         => 'subscribe',
                'type'             => 'SubscriptionIdentifier',
                'event_name'       => array('{api_name}.subscription.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of subscription attributes.',
                        'allowMultiple' => false,
                        'type'          => 'SubscriptionRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of subscription attributes including \'Name\'.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/subscription/{sub_name}',
        'description' => 'Operations for a specific push subscription.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getSubscriptionAttributes() - Retrieve attributes for the given subscription.',
                'nickname'         => 'getSubscriptionAttributes',
                'event_name'       => array('{api_name}.subscription.{subscription_name}.retrieve', '{api_name}.subscription_retrieved'),
                'type'             => 'SubscriptionAttributesResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'sub_name',
                        'description'   => 'Full ARN or simplified name of the subscription to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This retrieves the subscription, detailing its available properties.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'setSubscriptionAttributes() - Update a given subscription.',
                'nickname'         => 'setSubscriptionAttributes',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.subscription.{subscription_name}.update', '{api_name}.subscription_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'sub_name',
                        'description'   => 'Full ARN or simplified name of the subscription to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of subscription attributes.',
                        'allowMultiple' => false,
                        'type'          => 'SubscriptionAttributesRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of subscription attributes including \'Name\'.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'unsubscribe() - Delete a given subscription.',
                'nickname'         => 'unsubscribe',
                'notes'            => '',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.subscription.{subscription_name}.delete', '{api_name}.subscription_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'sub_name',
                        'description'   => 'Full ARN or simplified name of the subscription to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/app',
        'description' => 'Operations for push platform applications.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getApps() - Retrieve app definition for the given app.',
                'nickname'         => 'getApps',
                'event_name'       => array('{api_name}.app.list'),
                'type'             => 'GetAppsResponse',
                'responseMessages' => $_commonResponses,
                'notes'            => 'This describes the app, detailing its available properties.',
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listApps() - List apps available for the push service.',
                'nickname'         => 'listApps',
                'notes'            => 'See listed operations for each app available.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.app.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the apps in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createApp() - Create a given app.',
                'nickname'         => 'createApp',
                'type'             => 'AppIdentifier',
                'event_name'       => array('{api_name}.app.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of app attributes.',
                        'allowMultiple' => false,
                        'type'          => 'AppRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of app attributes including \'Name\'.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/app/{app_name}',
        'description' => 'Operations for a specific push platform application.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getAppAttributes() - Retrieve app definition for the given app.',
                'nickname'         => 'getAppAttributes',
                'event_name'       => array('{api_name}.app.{app_name}.retrieve', '{api_name}.app_retrieved'),
                'type'             => 'AppAttributesResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Full ARN or simplified name of the app to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This retrieves the app, detailing its available properties.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'setAppAttributes() - Update a given app.',
                'nickname'         => 'setAppAttributes',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.app.{app_name}.update', '{api_name}.app_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Full ARN or simplified name of the app to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of app attributes.',
                        'allowMultiple' => false,
                        'type'          => 'AppAttributesRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of app attributes including \'Name\'.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteApp() - Delete a given app.',
                'nickname'         => 'deleteApp',
                'notes'            => '',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.app.{app_name}.delete', '{api_name}.app_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Full ARN or simplified name of the app to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/app/{app_name}/endpoint',
        'description' => 'Operations for push application endpoints.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getEndpointsByApp() - Retrieve endpoints for the given application.',
                'nickname'         => 'getEndpointsByApp',
                'event_name'       => array('{api_name}.endpoint.list'),
                'type'             => 'GetEndpointsResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Name of the application to get endpoints on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This describes the endpoints, detailing its available properties.',
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listEndpointsByApp() - List endpoints available for the push service.',
                'nickname'         => 'listEndpointsByApp',
                'notes'            => 'See listed operations for each endpoint available.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.endpoint.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Name of the application to get endpoints on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the endpoints in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createAppEndpoint() - Create a endpoint for a given application.',
                'nickname'         => 'createAppEndpoint',
                'type'             => 'EndpointIdentifier',
                'event_name'       => array('{api_name}.endpoint.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'app_name',
                        'description'   => 'Name of the application to create endpoints on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of endpoint attributes.',
                        'allowMultiple' => false,
                        'type'          => 'AppEndpointRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of endpoint attributes including \'Name\'.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/endpoint',
        'description' => 'Operations for push application endpoints.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getEndpoints() - Retrieve endpoint definition for the given endpoint.',
                'nickname'         => 'getEndpoints',
                'notes'            => 'This describes the endpoint, detailing its available properties.',
                'event_name'       => array('{api_name}.endpoint.list'),
                'type'             => 'GetEndpointsResponse',
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'GET',
                'summary'          => 'listEndpoints() - List endpoints available for the push service.',
                'nickname'         => 'listEndpoints',
                'notes'            => 'See listed operations for each endpoint available.',
                'type'             => 'ComponentList',
                'event_name'       => array('{api_name}.endpoint.list'),
                'parameters'       => array(
                    array(
                        'name'          => 'names_only',
                        'description'   => 'Return only the names of the endpoints in an array.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => true,
                        'default'       => true,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createEndpoint() - Create a given endpoint.',
                'nickname'         => 'createEndpoint',
                'type'             => 'EndpointIdentifier',
                'event_name'       => array('{api_name}.endpoint.create'),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of endpoint attributes.',
                        'allowMultiple' => false,
                        'type'          => 'EndpointRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of endpoint attributes including \'Name\'.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/endpoint/{endpoint_name}',
        'description' => 'Operations for a specific push application endpoint.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getEndpointAttributes() - Retrieve endpoint definition for the given endpoint.',
                'nickname'         => 'getEndpointAttributes',
                'event_name'       => array('{api_name}.endpoint.{endpoint_name}.retrieve', '{api_name}.endpoint_retrieved'),
                'type'             => 'EndpointAttributesResponse',
                'parameters'       => array(
                    array(
                        'name'          => 'endpoint_name',
                        'description'   => 'Full ARN or simplified name of the endpoint to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This retrieves the endpoint, detailing its available properties.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'simplePublishEndpoint() - Send a message to the given endpoint.',
                'nickname'         => 'simplePublishEndpoint',
                'notes'            => 'Post data should be an array of endpoint publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.topic.{topic_name}.publish', '{api_name}.topic_published'),
                'parameters'       => array(
                    array(
                        'name'          => 'endpoint_name',
                        'description'   => 'Full ARN or simplified name of the endpoint to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic publish parameters.',
                        'allowMultiple' => false,
                        'type'          => 'SimplePublishEndpointRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'publishEndpoint() - Send a message to the given endpoint.',
                'nickname'         => 'publishEndpoint',
                'notes'            => 'Post data should be an array of endpoint publish properties.',
                'type'             => 'PublishResponse',
                'event_name'       => array('{api_name}.topic.{endpoint_name}.publish', '{api_name}.endpoint_published'),
                'parameters'       => array(
                    array(
                        'name'          => 'endpoint_name',
                        'description'   => 'Full ARN or simplified name of the endpoint to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of topic message parameters.',
                        'allowMultiple' => false,
                        'type'          => 'PublishEndpointRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'setEndpointAttributes() - Update a given endpoint.',
                'nickname'         => 'setEndpointAttributes',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.endpoint.{endpoint_name}.update', '{api_name}.endpoint_updated'),
                'parameters'       => array(
                    array(
                        'name'          => 'endpoint_name',
                        'description'   => 'Full ARN or simplified name of the endpoint to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Array of endpoint attributes.',
                        'allowMultiple' => false,
                        'type'          => 'EndpointAttributesRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of endpoint attributes including \'Name\'.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteEndpoint() - Delete a given endpoint.',
                'nickname'         => 'deleteEndpoint',
                'notes'            => '',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.endpoint.{endpoint_name}.delete', '{api_name}.endpoint_deleted'),
                'parameters'       => array(
                    array(
                        'name'          => 'endpoint_name',
                        'description'   => 'Full ARN or simplified name of the endpoint to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
            ),
        ),
    ),
);

$_commonAppAttributes = array(
    'PlatformCredential'   => array(
        'type'        => 'string',
        'description' => 'The credential received from the notification service.',
    ),
    'PlatformPrincipal'    => array(
        'type'        => 'string',
        'description' => 'The principal received from the notification service.',
    ),
    'EventEndpointCreated' => array(
        'type'        => 'string',
        'description' => 'Topic ARN to which EndpointCreated event notifications should be sent.',
    ),
    'EventEndpointUpdated' => array(
        'type'        => 'string',
        'description' => 'Topic ARN to which EndpointUpdated event notifications should be sent.',
    ),
    'EventEndpointDeleted' => array(
        'type'        => 'string',
        'description' => 'Topic ARN to which EndpointDeleted event notifications should be sent.',
    ),
    'EventDeliveryFailure' => array(
        'type'        => 'string',
        'description' => 'Topic ARN to which DeliveryFailure event notifications should be sent upon Direct Publish delivery failure (permanent) to one of the application\'s endpoints.',
    ),
);

$_commonEndpointAttributes = array(
    'CustomUserData' => array(
        'type'        => 'string',
        'description' => 'Arbitrary user data to associate with the endpoint.',
    ),
    'Enabled'        => array(
        'type'        => 'boolean',
        'description' => 'The flag that enables/disables delivery to the endpoint.',
    ),
    'Token'          => array(
        'type'        => 'string',
        'description' => 'The device token, also referred to as a registration id, for an app and mobile device.',
    ),
);
$_models = array(
    'GetTopicsResponse'              => array(
        'id'         => 'GetTopicsResponse',
        'properties' => array(
            'resource' => array(
                'type'        => 'Array',
                'description' => 'An array of identifying attributes for a topic, use either in requests.',
                'items'       => array(
                    '$ref' => 'TopicIdentifier',
                ),
            ),
        ),
    ),
    'TopicRequest'                   => array(
        'id'         => 'TopicRequest',
        'properties' => array(
            'Name' => array(
                'type'        => 'string',
                'description' => 'The name of the topic you want to create.',
                'required'    => true,
            ),
        ),
    ),
    'TopicIdentifier'                => array(
        'id'         => 'TopicIdentifier',
        'properties' => array(
            'Topic'    => array(
                'type'        => 'string',
                'description' => 'The topic\'s simplified name.',
            ),
            'TopicArn' => array(
                'type'        => 'string',
                'description' => 'The topic\'s Amazon Resource Name.',
            ),
        ),
    ),
    'TopicAttributesResponse'        => array(
        'id'         => 'TopicAttributesResponse',
        'properties' => array(
            'Topic'                   => array(
                'type'        => 'string',
                'description' => 'The topic\'s simplified name.',
            ),
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
    'TopicAttributesRequest'         => array(
        'id'         => 'TopicAttributesRequest',
        'properties' => array(
            'AttributeName'  => array(
                'type'        => 'string',
                'description' => 'The name of the attribute you want to set.',
                'enum'        => array('Policy', 'DisplayName', 'DeliveryPolicy'),
                'default'     => 'DisplayName',
                'required'    => true,
            ),
            'AttributeValue' => array(
                'type'        => 'string',
                'description' => 'The value of the attribute you want to set.',
            ),
        ),
    ),
    'GetSubscriptionsResponse'       => array(
        'id'         => 'GetSubscriptionsResponse',
        'properties' => array(
            'resource' => array(
                'type'        => 'Array',
                'description' => 'An array of identifying attributes for a subscription, use either in requests.',
                'items'       => array(
                    '$ref' => 'SubscriptionIdentifier',
                ),
            ),
        ),
    ),
    'SubscriptionRequest'            => array(
        'id'         => 'SubscriptionRequest',
        'properties' => array(
            'Topic'    => array(
                'type'        => 'string',
                'description' => 'The topic\'s simplified name or Amazon Resource Name.',
                'required'    => true,
            ),
            'Protocol' => array(
                'type'        => 'string',
                'description' => 'The protocol you want to use.',
                'enum'        => array('http', 'https', 'email', 'email-json', 'sms', 'sqs', 'application'),
                'required'    => true,
            ),
            'Endpoint' => array(
                'type'        => 'string',
                'description' => 'The endpoint that you want to receive notifications, formats vary by protocol.',
            ),
        ),
    ),
    'SubscriptionTopicRequest'       => array(
        'id'         => 'SubscriptionTopicRequest',
        'properties' => array(
            'Protocol' => array(
                'type'        => 'string',
                'description' => 'The protocol you want to use.',
                'enum'        => array('http', 'https', 'email', 'email-json', 'sms', 'sqs', 'application'),
                'required'    => true,
            ),
            'Endpoint' => array(
                'type'        => 'string',
                'description' => 'The endpoint that you want to receive notifications, formats vary by protocol.',
            ),
        ),
    ),
    'SubscriptionIdentifier'         => array(
        'id'         => 'SubscriptionIdentifier',
        'properties' => array(
            'Subscription'    => array(
                'type'        => 'string',
                'description' => 'The subscription\'s simplified name.',
            ),
            'SubscriptionArn' => array(
                'type'        => 'string',
                'description' => 'The subscription\'s Amazon Resource Name.',
            ),
        ),
    ),
    'SubscriptionAttributesResponse' => array(
        'id'         => 'SubscriptionAttributesResponse',
        'properties' => array(
            'Subscription'                 => array(
                'type'        => 'string',
                'description' => 'The subscription\'s simplified name.',
            ),
            'SubscriptionArn'              => array(
                'type'        => 'string',
                'description' => 'The subscription\'s Amazon Resource Name.',
            ),
            'TopicArn'                     => array(
                'type'        => 'string',
                'description' => 'The topic\'s Amazon Resource Name.',
            ),
            'Owner'                        => array(
                'type'        => 'string',
                'description' => 'The AWS account ID of the topic\'s owner.',
            ),
            'ConfirmationWasAuthenticated' => array(
                'type'        => 'boolean',
                'description' => 'True if the subscription confirmation request was authenticated.',
            ),
            'DeliveryPolicy'               => array(
                'type'        => 'string',
                'description' => 'The JSON serialization of the topic\'s delivery policy.',
            ),
            'EffectiveDeliveryPolicy'      => array(
                'type'        => 'string',
                'description' => 'The JSON serialization of the effective delivery policy that takes into account system defaults.',
            ),
        ),
    ),
    'SubscriptionAttributesRequest'  => array(
        'id'         => 'SubscriptionAttributesRequest',
        'properties' => array(
            'AttributeName'  => array(
                'type'        => 'string',
                'description' => 'The name of the attribute you want to set.',
                'enum'        => array('DeliveryPolicy', 'RawMessageDelivery'),
                'default'     => 'DeliveryPolicy',
                'required'    => true,
            ),
            'AttributeValue' => array(
                'type'        => 'string',
                'description' => 'The value of the attribute you want to set.',
            ),
        ),
    ),
    'GetAppResponse'                 => array(
        'id'         => 'GetAppResponse',
        'properties' => array(
            'resource' => array(
                'type'        => 'Array',
                'description' => 'An array of identifying attributes for a app, use either in requests.',
                'items'       => array(
                    '$ref' => 'AppIdentifier',
                ),
            ),
        ),
    ),
    'AppAttributes'                  => array(
        'id'         => 'AppAttributes',
        'properties' => $_commonAppAttributes,
    ),
    'AppRequest'                     => array(
        'id'         => 'AppRequest',
        'properties' => array(
            'Name'       => array(
                'type'        => 'string',
                'description' => 'Desired platform application name.',
                'required'    => true,
            ),
            'Platform'   => array(
                'type'        => 'string',
                'description' => 'One of the following supported platforms.',
                'enum'        => array('ADM', 'APNS', 'APNS_SANDBOX', 'GCM'),
                'required'    => true,
            ),
            'Attributes' => array(
                'type'        => 'AppAttributes',
                'description' => 'An array of key-value pairs containing platform-specified application attributes.',
            ),
        ),
    ),
    'AppIdentifier'                  => array(
        'id'         => 'AppIdentifier',
        'properties' => array(
            'Application'            => array(
                'type'        => 'string',
                'description' => 'The app\'s simplified name.',
            ),
            'PlatformApplicationArn' => array(
                'type'        => 'string',
                'description' => 'The app\'s Amazon Resource Name.',
            ),
        ),
    ),
    'AppAttributesResponse'          => array(
        'id'         => 'AppAttributesResponse',
        'properties' => array(
            'Application'            => array(
                'type'        => 'string',
                'description' => 'The app\'s simplified name.',
            ),
            'PlatformApplicationArn' => array(
                'type'        => 'string',
                'description' => 'The app\'s Amazon Resource Name.',
            ),
            'EventEndpointCreated'   => array(
                'type'        => 'string',
                'description' => 'Topic ARN to which EndpointCreated event notifications should be sent.',
            ),
            'EventEndpointUpdated'   => array(
                'type'        => 'string',
                'description' => 'Topic ARN to which EndpointUpdated event notifications should be sent.',
            ),
            'EventEndpointDeleted'   => array(
                'type'        => 'string',
                'description' => 'Topic ARN to which EndpointDeleted event notifications should be sent.',
            ),
            'EventDeliveryFailure'   => array(
                'type'        => 'string',
                'description' => 'Topic ARN to which DeliveryFailure event notifications should be sent upon Direct Publish delivery failure (permanent) to one of the application\'s endpoints.',
            ),
        ),
    ),
    'AppAttributesRequest'           => array(
        'id'         => 'AppAttributesRequest',
        'properties' => array(
            'Attributes' => array(
                'type'        => 'AppAttributes',
                'description' => 'Mutable attributes on the endpoint.',
                'required'    => true,
            ),
        ),
    ),
    'GetEndpointsResponse'           => array(
        'id'         => 'GetEndpointsResponse',
        'properties' => array(
            'resource' => array(
                'type'        => 'Array',
                'description' => 'An array of identifying attributes for a topic, use either in requests.',
                'items'       => array(
                    '$ref' => 'EndpointIdentifier',
                ),
            ),
        ),
    ),
    'AppEndpointRequest'             => array(
        'id'         => 'AppEndpointRequest',
        'properties' => array(
            'Token'          => array(
                'type'        => 'string',
                'description' => 'Unique identifier created by the notification service for an app on a device.',
                'required'    => true,
            ),
            'CustomUserData' => array(
                'type'        => 'string',
                'description' => 'Arbitrary user data to associate with the endpoint.',
            ),
            'Attributes'     => array(
                'type'        => 'Array',
                'description' => 'An array of key-value pairs containing endpoint attributes.',
                'items'       => array(
                    '$ref' => 'MessageAttribute',
                ),
            ),
        ),
    ),
    'EndpointRequest'                => array(
        'id'         => 'EndpointRequest',
        'properties' => array(
            'Application'    => array(
                'type'        => 'string',
                'description' => 'The application\'s simplified name or Amazon Resource Name.',
                "required"    => true,
            ),
            'Token'          => array(
                'type'        => 'string',
                'description' => 'Unique identifier created by the notification service for an app on a device.',
                'required'    => true,
            ),
            'CustomUserData' => array(
                'type'        => 'string',
                'description' => 'Arbitrary user data to associate with the endpoint.',
            ),
            'Attributes'     => array(
                'type'        => 'Array',
                'description' => 'An array of key-value pairs containing endpoint attributes.',
                'items'       => array(
                    '$ref' => 'MessageAttribute',
                ),
            ),
        ),
    ),
    'EndpointIdentifier'             => array(
        'id'         => 'EndpointIdentifier',
        'properties' => array(
            'Endpoint'    => array(
                'type'        => 'string',
                'description' => 'The endpoint\'s simplified name.',
            ),
            'EndpointArn' => array(
                'type'        => 'string',
                'description' => 'The endpoint\'s Amazon Resource Name.',
            ),
        ),
    ),
    'EndpointAttributesResponse'     => array(
        'id'         => 'EndpointAttributesResponse',
        'properties' => array(
            'Endpoint'       => array(
                'type'        => 'string',
                'description' => 'The endpoint\'s simplified name.',
            ),
            'EndpointArn'    => array(
                'type'        => 'string',
                'description' => 'The endpoint\'s Amazon Resource Name.',
            ),
            'CustomUserData' => array(
                'type'        => 'string',
                'description' => 'Arbitrary user data to associate with the endpoint.',
            ),
            'Enabled'        => array(
                'type'        => 'boolean',
                'description' => 'The flag that enables/disables delivery to the endpoint.',
            ),
            'Token'          => array(
                'type'        => 'string',
                'description' => 'The device token, also referred to as a registration id, for an app and mobile device.',
            ),
        ),
    ),
    'EndpointAttributes'             => array(
        'id'         => 'EndpointAttributes',
        'properties' => $_commonEndpointAttributes,
    ),
    'EndpointAttributesRequest'      => array(
        'id'         => 'EndpointAttributesRequest',
        'properties' => array(
            'Attributes' => array(
                'type'        => 'EndpointAttributes',
                'description' => 'Mutable attributes on the endpoint.',
                'required'    => true,
            ),
        ),
    ),
    'TopicMessage'                   => array(
        'id'         => 'TopicMessage',
        'properties' => array(
            'default' => array(
                'type'        => 'string',
                'description' => 'This is sent when the message type is not specified below.',
                'required'    => true,
            ),
            'email'   => array(
                'type'        => 'string',
                'description' => 'Message sent to all email or email-json subscriptions.',
            ),
            'sqs'     => array(
                'type'        => 'string',
                'description' => 'Message sent to all AWS SQS subscriptions.',
            ),
            'http'    => array(
                'type'        => 'string',
                'description' => 'Message sent to all HTTP subscriptions.',
            ),
            'https'   => array(
                'type'        => 'string',
                'description' => 'Message sent to all HTTPS subscriptions.',
            ),
            'sms'     => array(
                'type'        => 'string',
                'description' => 'Message sent to all SMS subscriptions.',
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
    'MessageAttributeData'               => array(
        'id'         => 'MessageAttributeData',
        'properties' => array(
            'DataType'    => array(
                'type'        => 'string',
                'description' => 'Amazon SNS supports the following logical data types: String, Number, and Binary.',
                'required'    => true,
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
    'MessageAttribute'               => array(
        'id'         => 'MessageAttribute',
        'properties' => array(
            '_user_defined_name_'    => array(
                'type'        => 'MessageAttributeData',
                'description' => 'The name of the message attribute as defined by the user or specified platform.',
            ),
        ),
    ),
    'SimplePublishRequest'           => array(
        'id'         => 'SimplePublishRequest',
        'properties' => array(
            'Topic'             => array(
                'type'        => 'string',
                'description' => 'The simple name or ARN of the topic you want to publish to. Required if endpoint not given.',
            ),
            'Endpoint'          => array(
                'type'        => 'string',
                'description' => 'The simple name or ARN of the endpoint you want to publish to. Required if topic not given.',
            ),
            'Message'           => array(
                'type'        => 'string',
                'description' => 'The message you want to send to the topic, sends the same message to all transport protocols. ',
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'PublishRequest'                 => array(
        'id'         => 'PublishRequest',
        'properties' => array(
            'Topic'             => array(
                'type'        => 'string',
                'description' => 'The simple name or ARN of the topic you want to publish to. Required if endpoint not given.',
            ),
            'Endpoint'          => array(
                'type'        => 'string',
                'description' => 'The simple name or ARN of the endpoint you want to publish to. Required if topic not given.',
            ),
            'Message'           => array(
                'type'        => 'TopicMessage',
                'description' => 'The message you want to send to the topic. The \'default\' field is required.',
                'required'    => true,
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageStructure'  => array(
                'type'        => 'string',
                'description' => 'Set MessageStructure to "json".',
                'default'     => 'json',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'SimplePublishTopicRequest'      => array(
        'id'         => 'SimplePublishTopicRequest',
        'properties' => array(
            'Message'           => array(
                'type'        => 'string',
                'description' => 'The message you want to send to the topic, sends the same message to all transport protocols.',
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'PublishTopicRequest'            => array(
        'id'         => 'PublishTopicRequest',
        'properties' => array(
            'Message'           => array(
                'type'        => 'TopicMessage',
                'description' => 'The message you want to send to the topic. The \'default\' field is required.',
                'required'    => true,
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageStructure'  => array(
                'type'        => 'string',
                'description' => 'Set MessageStructure to "json".',
                'default'     => 'json',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'SimplePublishEndpointRequest'   => array(
        'id'         => 'SimplePublishEndpointRequest',
        'properties' => array(
            'Message'           => array(
                'type'        => 'string',
                'description' => 'The message you want to send to the topic, sends the same message to all transport protocols.',
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'PublishEndpointRequest'         => array(
        'id'         => 'PublishEndpointRequest',
        'properties' => array(
            'Message'           => array(
                'type'        => 'TopicMessage',
                'description' => 'The message you want to send to the topic. The \'default\' field is required.',
                'required'    => true,
            ),
            'Subject'           => array(
                'type'        => 'string',
                'description' => 'Optional parameter to be used as the "Subject" line when the message is delivered to email endpoints.',
            ),
            'MessageStructure'  => array(
                'type'        => 'string',
                'description' => 'Set MessageStructure to "json".',
                'default'     => 'json',
            ),
            'MessageAttributes' => array(
                'type'        => 'MessageAttribute',
                'description' => 'An associative array of string-data pairs containing user-specified message attributes.',
            ),
        ),
    ),
    'PublishResponse'                => array(
        'id'         => 'PublishResponse',
        'properties' => array(
            'MessageId' => array(
                'type'        => 'string',
                'description' => 'Unique identifier assigned to the published message.',
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models );

unset( $_commonResponses, $_models );

return $_base;