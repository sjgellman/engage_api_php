<?php
    // Uses Composer.
    require 'vendor/autoload.php';
    use GuzzleHttp\Client;
    use Symfony\Component\Yaml\Yaml;

    // App to search for donation records and show them on the console.
    //
    // Usage:
    //
    //  php src/search_all_donations.php -login config.yaml
    //

    // "config.yaml" is a YAML file. It contains these fields.
    /*
    token:          "your-incredibly-long-token"
    identifierType: FUNDRAISE
    modifiedFrom:   "2018-07-01T00:00:00.000Z"
    modifiedTo:     "2018-07-31T23:59:59.999Z"

    Output is typical lazy donation representation.
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
        $cred =  Yaml::parseFile($filename);
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
            "identifierType",
            "modifiedFrom",
            "modifiedTo"
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

    // Retrieve transactions and display the applicable ones.
    function getTransactions($cred, $offset, $count)
    {
        $headers = [
            'authToken' => $cred['token'],
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'payload' => [
                'type' => $cred["identifierType"],
                'modifiedFrom' => $cred['modifiedFrom'],
                'modidifedTo' => $cred['modifiedTo'],
                'offset' => $offset,
                'count' => $count
            ],
        ];
        $method = 'POST';
        $uri = $cred['host'];
        $command = '/api/integration/ext/v1/activities/search';
        $client = new GuzzleHttp\Client([
            'base_uri' => $uri,
            'headers' => $headers,
        ]);
        try {
            $response = $client->request($method, $command, [
                'json' => $payload,
            ]);
            $data = json_decode($response->getBody());
            $payload = $data->payload;
            $count = $payload->count;
            if ($count == 0) {
                return null;
            }
            return $payload->activities;
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
            // var_dump($e);
            return null;
        }
    }

    function main()
    {
        $cred = initialize();
        $offset = 0;
        $count = 20;
        while ($count > 0) {
            $activities = getTransactions($cred, $offset, $count);
            if (is_null($activities)) {
                $count = 0;
            } else {
                $i = 0;
                foreach ($activities as $s) {
                    fprintf(STDOUT, "[%3d:%3d] %s %-30s %s %s %s %s\n",
                        $offset,
                        $i,
                        $s->activityId,
                        $s->activityFormName,
                        $s->activityDate,
                        $s->activityType,
                        $s->donationId,
                        $s->totalReceivedAmount);
                    $i++;
                    // foreach ($s->transactions as $t) {
                    //     fprintf(STDOUT, "Transaction: %s %s %s %s %s %s %s\n",
                    //         $t->type,
                    //         $t->reason,
                    //         $t->date,
                    //         $t->amount,
                    //         $t->deductibleAmount,
                    //         $t->feesPaid,
                    //         $t->gatewayTransactionId);
                    // }
                }
                $count = $i;
            }
            $offset += $count;
        }
        fprintf(STDOUT, "[%5d:00] end of search\n",
            $offset,
            $i);
    }

    main();

?>
