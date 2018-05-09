<?php
    // Uses Composer.
    require 'vendor/autoload.php';
    use GuzzleHttp\Client;
    
    $headers = [
        'authToken' => 'YOUR-INCREDIBLY-LONG-AUTH-TOKEN-HERE'
    ];
    // Payload matches the `curl` bash script.
    $payload = [ 'payload' => [
        	'count' => 10,
        	'offset' => 0,
            'identifiers' => ["lisa@obesityaction.org"],
            "identifierType" => "EMAIL_ADDRESS"
        ]
    ];
    $method = 'POST';
    $uri = 'https://api.salsalabs.org';
    $command = '/api/integration/ext/v1/supporters/search';
    
    $client = new GuzzleHttp\Client([
        'base_uri' => $uri,
        'headers'  => $headers
    ]);
    try {
        $response = $client->request($method, $command, [
            'json'     => $payload
        ]);

        $data = json_decode($response -> getBody());
        echo $data["supporterId"];
        echo json_encode($data, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
    // var_dump($e);
}

?>