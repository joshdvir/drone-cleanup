<?php
/*

Script that cleans up old builds of all repos of a drone server.

Dependencies:

$ # having PHP and composer installed
$ composer require "guzzlehttp/guzzle=^6.3"

Configuration:

$ export DRONE_SERVER=https://drone.example.com
$ export DRONE_TOKEN=yourDroneToken
$ export DRONE_RETENTION_DAYS=60     # optional - by default this is 31

Usage:

$ php cleanup.php

 */

require 'vendor/autoload.php';

if (!getenv('DRONE_SERVER')) {
	echo "Please set environment variable DRONE_SERVER.\n";
	exit;
}
if (!getenv('DRONE_TOKEN')) {
	echo "Please set environment variable DRONE_TOKEN.\n";
	exit;
}
$RETENTION_DAYS = 31;
if (getenv('DRONE_RETENTION_DAYS')) {
	$RETENTION_DAYS = getenv('DRONE_RETENTION_DAYS');
}

$DRONE_URL = getenv('DRONE_SERVER');
$DRONE_TOKEN = getenv('DRONE_TOKEN');

$client = new GuzzleHttp\Client([
	'headers' => [
		'Authorization' => 'Bearer ' . $DRONE_TOKEN,
	],
	'base_uri' => $DRONE_URL,
]);

function getRepos(\GuzzleHttp\Client $client): array {
	$res = $client->request('GET', '/api/user/repos');

	if ($res->getStatusCode() !== 200) {
		throw new \Exception('Non-200 status code');
	}

	$repos = json_decode($res->getBody(), true);
	$reposToCheck = [];

	foreach ($repos as $repo) {
		if ($repo['counter'] === 0) {
			continue;
		}
		$reposToCheck[] = $repo['slug'];
	}

	return $reposToCheck;
}
function getBuildNumber(\GuzzleHttp\Client $client, string $slug, int $days): int {

	$oneMonthAgo = time() - (60 * 60 * 24 * $days);

	$page = 1;
	$buildNumber = 0;
	do {
		$res = $client->request('GET', '/api/repos/' . $slug . '/builds?page=' . $page);
		$builds = json_decode($res->getBody(), true);

		$count = count($builds);
		if ($count !== 0 && $builds[$count - 1]['started'] < $oneMonthAgo) {
			foreach ($builds as $build) {
				if ($build['started'] < $oneMonthAgo) {
					$buildNumber = $build['number'];
					break;
				}
			}
		}

		$page += 1;

	} while ($count !== 0 && $buildNumber === 0);

	return $buildNumber;
}

echo "Fetching repos ...\n";
$repos = getRepos($client);

echo count($repos) . " repos found.\n";

echo "Fetching builds older than $RETENTION_DAYS days.\n";

foreach ($repos as $slug) {
	$buildNumber = getBuildNumber($client, $slug, $RETENTION_DAYS);

	if ($buildNumber !== 0) {
		echo "Deleting builds from $slug before or equal to $buildNumber\n";

		// deletion is based on "before NUMBER" so we add one, to delete the entry that is older than a month as well
		$buildNumber += 1;
		$res = $client->request('DELETE', "api/repos/$slug/builds?before=$buildNumber");

		if ($res->getStatusCode() !== 204) {
			"deletion of $slug with before=$buildNumber failed\n";
			echo $res->getBody();
			echo "\n\n";
		}
	} else {
		echo "Nothing to delete from $slug.\n";
	}
}

echo "Done.\n";
