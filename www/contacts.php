<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../components/User.php';

/**
 * Contacts service
 * 
 * Pure API: https://www.google.com/m8/feeds/groups/default/full?alt=json&v=3.0&max-results=1000&oauth_token={$accessToken}
 */

// Configuration
$config = require __DIR__ . '/../config.inc.php';

$token = User::getToken();
if (!User::getToken()) {
    
    header('Location: ./');
}

$client = new Google_Client();
$client->setApplicationName('Google API');
$client->setAuthConfig($config['authConfig']);
$client->setRedirectUri($config['redirectUri']);
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setAccessToken($token);
// Refresh the token if it's expired.
if ($client->isAccessTokenExpired()) {

    $token = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    User::saveToken($token);
}

// Get Service
// $service = new Google_Service_Drive($client);
// var_dump($client);

// $client->addScope("https://www.google.com/m8/feeds");
// $authUrl = $client->createAuthUrl();
// echo $authUrl;
$accessToken = $token['access_token'];

try {

	echo "https://www.google.com/m8/feeds/contacts/default/full?alt=json&v=3.0&max-results=1000&oauth_token={$accessToken}";exit;

    // Create a Guzzle Client auth by Google bearer
    $httpClient = $client->authorize();
    $httpOptions = [
        'headers' => [
            // Google m8 feed 403 for others user-agent
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36',
        ]
    ];
    // API URL from Service Scope
	$serviceUrl = $config['serviceScopes']['contacts'][0];

	/**
	 * Create
	 */
	$url = "{$serviceUrl}/contacts/default/full";
	// $url = "https://dev.yidas.com/tool/get-headers";
	// List Contacts API request
	$httpOptions = array_merge($httpOptions, [
		'headers' => [
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36',
			'Content-type' => 'application/json',
		],
		'json' => [],
	]);
	// print_r($httpOptions);exit;
	$res = $httpClient->request('POST', $url, $httpOptions);
	echo $res->getStatusCode();
	echo $res->getBody();
	$result = json_decode($res->getBody(), true);
	print_r($result);exit;
	
	/**
	 * Group fetch (`Contacts` excludes `other contacts`)
	 */
	$url = 'https://www.google.com/m8/feeds/groups/default/full?alt=json&v=3.0&';
	// List Contacts API request
	$res = $httpClient->request('GET', $url, $httpOptions);

	$statusCode = $res->getStatusCode();
    // Check status code
    if ($statusCode != 200) {
        
        throw new Exception("API request failed", $statusCode);
    }
	// API Result
	$result = json_decode($res->getBody(), true);
	// print_r($result);exit;

	$groupID = $result['feed']['entry'][0]['id']['$t'];

    
    /**
     * Contacts API fetch
     */
	// $url = "{$serviceUrl}/contacts/default/full?alt=json&v=3.0&max-results=1000";
	$url = "{$serviceUrl}/contacts/default/full?alt=json&v=3.0&group={$groupID}&max-results=1000";
	// List Contacts API request
    $res = $httpClient->request('GET', $url, $httpOptions);

    $statusCode = $res->getStatusCode();
    // Check status code
    if ($statusCode != 200) {
        
        throw new Exception("API request failed", $statusCode);
    }
	// API Result
    $result = json_decode($res->getBody(), true);


    // $url = 'https://www.google.com/m8/feeds/groups/default/full?alt=json&v=3.0&max-results=1000&oauth_token='.$accessToken;
    // $url = 'http://www.google.com/feeds/contacts/groups/default/base/6?alt=json&v=3.0&max-results=1000&oauth_token='.$accessToken;
    // echo $url;exit;
    // $url = 'https://www.google.com/m8/feeds/contacts/groups/default/base/6?alt=json&v=3.0&max-results=1000&oauth_token='.$accessToken;

    // All contacts from token user
    // $url = 'https://www.google.com/m8/feeds/contacts/default/thin?alt=json&v=3.0&max-results=1000&oauth_token='.$accessToken;


    // $response = @file_get_contents($url, false, stream_context_create([
    //     'http' => [
    //         'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36",
    //     ]
    // ]));
    // $status = $http_response_header[0];
    // echo $status;echo $response;exit;

    // $result = json_decode($response, true);
    // print_r($result);exit;

    /**
	 * Parser
	 */
    $contacts = [];
    $rawContacts = & $result['feed']['entry'];
    // print_r($rawContacts);exit;
    foreach ((array)$rawContacts as $key => $raw) {
        
        $data = [];
        $data['id'] = isset($raw['id']['$t']) ? $raw['id']['$t'] : null;
        $data['updated'] = isset($raw['updated']['$t']) ? $raw['updated']['$t'] : null;
        $data['title'] = isset($raw['title']['$t']) ? $raw['title']['$t'] : null;
        $data['name'] = isset($raw['gd$name']['gd$fullName']['$t']) ? $raw['gd$name']['gd$fullName']['$t'] : null;
        $data['phone'] = isset($raw['gd$phoneNumber'][0]['$t']) ? $raw['gd$phoneNumber'][0]['$t'] : null;
        $data['email'] = isset($raw['gd$email'][0]['address']) ? $raw['gd$email'][0]['address'] : null;

        $contacts[] = $data;
    }
    print_r($contacts);exit;

} catch (Exception $e) {

    switch ($e->getCode()) {
        case '403':
            echo 'Access Denied: You don\'t have permissions';
            break;
        
        default:
            echo $e->getMessage();
            break;
    }
    
    exit;
}
    

?>

<!DOCTYPE html>
<html>
<head>
    <title>Google API - Drive</title>
</head>
<body>

    <h3><a href="./">Google API</a> - Drive</h3>

    <form action="?op=upload" method="post" enctype="multipart/form-data">
        Select a file to upload:
        <input type="file" name="file_upload">
        <input type="submit" value="Upload" name="submit">
    </form>

    <hr/>

    <?php if (count($results->getFiles()) == 0): ?>
        No Result: Your Google Drive have no record.
    <?php else: ?>
        <ul>
        <?php foreach ($results->getFiles() as $file): ?>
            <li><?=printf("%s (%s)\n", $file->getName(), $file->getId())?></li>
        <?php endforeach ?>
        </ul>
    <?php endif ?>

</body>
</html>