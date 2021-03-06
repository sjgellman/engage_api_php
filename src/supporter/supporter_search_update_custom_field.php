<?php
    // Uses Composer.
    require 'vendor/autoload.php';
    use GuzzleHttp\Client;
    use Symfony\Component\Yaml\Yaml;

    // App to look up a supporter by email.  Once found, then
    // update a custom field with a value.
    // Example contents:
    /*         
        email: someone@whatever.biz
        token: Your-incredibly-long-Engage-token-here
        fieldName: custom field name.
        fieldValue: mew custom field value
    */

     // Retrieve the runtime parameters and validate them.
     function initialize()
     {
         $shortopts = "";
         $longopts = array(
             "login:"
         );
         $options = getopt($shortopts, $longopts);
         if (false == array_key_exists('login', $options)) {
             exit("\nYou must provide a parameter file with --login!\n");
         }
         $filename = $options['login'];
         $cred = Yaml::parseFile($filename);
         validateCredentials($cred, $filename);
         return $cred;
     }
 
     // Validate the contents of the provided credential file.
     // All fields are required.  Exits on errors.
     function validateCredentials($cred, $filename) {
         $errors = false;
         $fields = array(
             "token",
             "host",
             "email",
             "fieldName",
             "fieldValue"
         );
         foreach ($fields as $f) {
             if (false == array_key_exists($f, $cred)) {
                 printf("Error: %s must contain a %s.\n", $filename, $f);
                 $errors = true;
             }
         }
         if ($errors) {
             exit("Too many errors, terminating.\n");
         }
     }
 
    // Return the supporter record for the email in the credentials.
    // @param array  $cred  Contents of YAML credentials file
    //
    function getSupporter($cred) {
        $headers = [
            'authToken' => $cred['token'],
            'Content-Type' => 'application/json'
        ];
        // 'identifiers' in the YAML file is an array of identifiers.
        // 'identifierType' is one of the official identifier types.
        // @see https://help.salsalabs.com/hc/en-us/articles/224470107-Supporter-Data
        $payload = [
            'payload' => [
                'count' => 10,
                'offset' => 0,
                'identifiers' => [ $cred['email'] ],
                'identifierType' => "EMAIL_ADDRESS"
            ]
        ];
        // printf("Payload:\n%s\n", json_encode($payload, JSON_PRETTY_PRINT));

        $method = 'POST';
        $uri = 'https://api.salsalabs.org';
        $uri = 'https://hq.uat.igniteaction.net';
        $command = '/api/integration/ext/v1/supporters/search';
        $client = new GuzzleHttp\Client([
            'base_uri' => $uri,
            'headers'  => $headers
        ]);
        $response = $client->request($method, $command, [
            'json'     => $payload
        ]);
        $data = json_decode($response -> getBody());
        // The first record is the one for the email address.
        // If it's not found, then we stop here.
        $supporter = $data -> payload -> supporters[0];
        if ($supporter -> result != "FOUND") {
            return NULL;
        }
        return $supporter;
    }

    function seeCustomField($cf) {
        // Testing may unset some of these fields. These statements provide guard logic.
        $fieldId = property_exists($cf, 'fieldId') ? $cf->fieldId : "";
        $name = property_exists($cf, 'name') ? $cf->name : "";
        $type = property_exists($cf, 'type') ? $cf->type : "";
        $value = property_exists($cf, 'value') ? $cf->value : "";
        // printf("\t%s\n", json_encode($cf));

        printf("\t%s %s %s = '%s'\n",
            $fieldId,
            $name,
            $type,
            $value);
        if (property_exists($cf, 'errors')) {
            printf("\t*** %s\n", $cf->errors[0]->message);
        }
    }

    // Update a custom field using `$cred` as a guide.
    //
    // @param array  $cred
    // @param array  supporter
    //
    // @see https://help.salsalabs.com/hc/en-us/articles/224470107-Supporter-Data#partial-updates
    //
    function update($cred, $supporter) {
        $headers = [
            'authToken' => $cred['token'],
            'Content-Type' => 'application/json'
        ];

        // Search for the custom field and change its value.
        
        foreach ($supporter->customFieldValues as $cf) {
            if ($cf -> name == $cred["fieldName"]) {
                $cf -> value = $cred["fieldValue"];

                //Unsetting a field removes it from the current object.
                //Uncomment these to see what happens...
                //unset($cf->fieldId);
                //unset($cf->name);
                //unset($cf->type);
            }
        };

        $payload = [
            'payload' => [
                'supporters' => [ $supporter ]
            ]
        ];

        // echo "\nUpdate Payload:\n";
        // printf("%s\n", json_encode($payload, JSON_PRETTY_PRINT));
        // echo "\n";

        $method = 'PUT';
        $uri = 'https://api.salsalabs.org';
        $uri = 'https://hq.uat.igniteaction.net';
        $command = '/api/integration/ext/v1/supporters';
        $client = new GuzzleHttp\Client([
            'base_uri' => $uri,
            'headers'  => $headers
        ]);
        $response = $client->request($method, $command, [
            'json'     => $payload
        ]);
        $data = json_decode($response -> getBody());

        // echo "\nUpdate response:\n";
        // printf("%s\n", json_encode($data->payload, JSON_PRETTY_PRINT));
        // echo "\n";

        echo "\nError analysis:\n";
        foreach ($data->payload->supporters[0]->customFieldValues as $cf) {
            if (property_exists($cf, 'errors')) {
                seeCustomField($cf);
            }
       }
    }           
    
    
    // Main app.  Does the work.
    function main() {
        $cred = initialize();
        $supporter = getSupporter($cred);
        if (is_null($supporter)) {
            printf("Sorry, can't find supporter for '%s'.\n", $cred["email"]);
            exit();
        };
        // printf("Supporter is %s\n", json_encode($supporter, JSON_PRETTY_PRINT));

        // Display the current values, including the one that we want to change.
        echo("\nBefore:\n");
        foreach ($supporter->customFieldValues as $cf) {
            if ($cf->name == $cred["fieldName"]) {
                $cf->value = $cred["fieldValue"];
            }
            seeCustomField($cf);
        }

        // Update to Engage.
        update($cred, $supporter);

        // Show what Engage returns.  Note that custom field values have an 
        // optional "errors" field that will describe any errors.
        echo("\nAfter:\n");
        $supporter = getSupporter($cred);
        foreach ($supporter->customFieldValues as $cf) {
            seeCustomField($cf);
         }
    }
    main();
?>
