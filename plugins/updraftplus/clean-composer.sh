#!/bin/bash

# This script is for removing unwanted stuff that Composer pulls in upon an update (documentation, tests, etc.) - stuff that's just bloat as far as the plugin packaging is concerned.

# Abort if not running in shell environment
[[ -z $SHELL ]] && exit

rm -rf vendor/eher/oauth/test

# Un-needed Rackspace/PHP-Opencloud components
for i in doc tests samples; do
	rm -rf vendor/rackspace/php-opencloud/$i
done
# Referenced in the auto-loader
mkdir vendor/rackspace/php-opencloud/tests

for i in Autoscale CloudMonitoring Compute Database DNS Image LoadBalancer Networking Orchestration Queues Volume; do
	rm -rf vendor/rackspace/php-opencloud/lib/OpenCloud/$i
done

for i in docs phing tests; do
	rm -rf vendor/guzzle/guzzle/$i
done
# Referenced in the auto-loader
mkdir vendor/guzzle/guzzle/tests

# Un-wanted AWS stuff
for i in AutoScaling CloudSearchDomain CognitoIdentity DirectConnect ElasticBeanstalk OpsWorks StorageGateway CloudFormation CloudTrail CognitoSync DynamoDb ElasticLoadBalancing ImportExport Rds Ses Sts CloudFront CloudWatch Ec2 ElasticTranscoder Kinesis Redshift SimpleDb Support CloudHsm CloudWatchLogs ConfigService Ecs Emr Kms Route53 Sns Swf CloudSearch CodeDeploy DataPipeline ElastiCache Glacier Lambda Route53Domains Sqs; do
	rm -rf vendor/aws/aws-sdk-php/src/Aws/$i
done

echo "Important: remember to disable the PSR-4 autoloading, to prevent fatals caused by plugins with older versions of Composer"