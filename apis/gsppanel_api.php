<?php

function log_msg() {

}

if (!function_exists('str_random')) {
    function str_random($length = 6) {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }
}

function pterodactyl_GetHostname(array $params) {
    $hostname = $params['panel_url'];
//    if (ip2long($hostname) !== false) $hostname = 'http://' . $hostname;

//    if (substr($hostname, -1) === '/') return substr($hostname, 0, strlen($hostname) - 1);

    return $hostname;
}

function pterodactyl_API(array $params, array $data = [], $dontLog = false) {
    $url = pterodactyl_GetHostname($params) . '/api/webapi.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    $responseData = json_decode($response, true);
    //$responseData['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return $responseData;
}
function pterodactyl_ConfigOptions() {
    return [
        "server_name" => [
            "FriendlyName" => "Server Name",
            "Description" => "The name of the server as shown on the panel (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
    ];
}

function pterodactyl_TestConnection(array $params) {
    $solutions = [
        0 => "Most likely hostname is configured wrong causing the request never get executed.",
        401 => "Authorization header either missing or not provided.",
        403 => "Double check the password (which should be the Application Key).",
        404 => "Result not found.",
        422 => "Validation error.",
        500 => "Panel errored, check panel logs.",
    ];

    $err = "";
    try {
        $response = pterodactyl_API($params);

        if ($response['status_code'] !== 200) {
            $status_code = $response['status_code'];
            $err = "Invalid status_code received: " . $status_code . ". Possible solutions: "
                . (isset($solutions[$status_code]) ? $solutions[$status_code] : "None.");
        } else {
            if ($response['meta']['pagination']['count'] === 0) {
                $err = "Authentication successful, but no nodes are available.";
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }

    return [
        "success" => $err === "",
        "error" => $err,
    ];
}

function pterodactyl_GenerateUsername($length = 8) {
    $returnable = false;
    $generated = time();
    while (!$returnable) {
        $generated = str_random($length);
        if (preg_match('/[A-Z]+[a-z]+[0-9]+/', $generated)) {
            $returnable = true;
        }
    }
    return $generated;
}

function pterodactyl_GetOption(array $params, $id, $default = NULL) {
    $options = pterodactyl_ConfigOptions();

    $friendlyName = $options[$id]['FriendlyName'];
    if (isset($params[$friendlyName]) && $params[$friendlyName] !== '') {
        return $params[$friendlyName];
    } else if (isset($params[$id]) && $params[$id] !== '') {
        return $params[$id];
    } else if (isset($params['customfields'][$friendlyName]) && $params['customfields'][$friendlyName] !== '') {
        return $params['customfields'][$friendlyName];
    } else if (isset($params['customfields'][$id]) && $params['customfields'][$id] !== '') {
        return $params['customfields'][$id];
    }

    $found = false;
    $i = 0;
    foreach (pterodactyl_ConfigOptions() as $key => $value) {
        $i++;
        if ($key === $id) {
            $found = true;
            break;
        }
    }

    if ($found && isset($params['configoption' . $i]) && $params['configoption' . $i] !== '') {
        return $params['configoption' . $i];
    }

    return $default;
}

function pterodactyl_CreateAccount(array $params) {
    try {
        // TODO: Create logic if account exists
        // $serverId = pterodactyl_GetServerID($params);
        // if (isset($serverId)) throw new Exception('Failed to create server because it is already created.');

        $userResult = pterodactyl_API($params, 'users/external/' . $params['clientsdetails']->id_code);

        if ($userResult['status_code'] === 404) {
            $userResult = pterodactyl_API($params, 'users?search=' . urlencode($params['clientsdetails']->email));

            if ($userResult['meta']['pagination']['total'] === 0) {
                $userResult = pterodactyl_API($params,  [
                    'username' => pterodactyl_GenerateUsername(),
                    'email' => $params['clientsdetails']->email
                ], 'POST');
            } else {
                foreach ($userResult['data'] as $key => $value) {
                    if ($value['attributes']['email'] === $params['clientsdetails']->email) {
                        $userResult = array_merge($userResult, $value);
                        break;
                    }
                }
                $userResult = array_merge($userResult, $userResult['data'][0]);
            }
        }

        if ($userResult['status_code'] === 200 || $userResult['status_code'] === 201) {
            $userId = $userResult['attributes']['id'];
        } else {
            throw new Exception('Failed to create user, received error code: ' . $userResult['status_code'] . '. Enable module debug log for more info.');
        }


        $nestId = pterodactyl_GetOption($params, 'nest_id');
        $eggId = pterodactyl_GetOption($params, 'egg_id');


        $eggData = pterodactyl_API($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if ($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');


        $environment = [];
        foreach ($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $default = $attr['default_value'];
            $friendlyName = pterodactyl_GetOption($params, $attr['name']);
            $envName = pterodactyl_GetOption($params, $attr['env_variable']);

            if (isset($friendlyName)) $environment[$var] = $friendlyName;
            elseif (isset($envName)) $environment[$var] = $envName;
            else $environment[$var] = $default;
        }

        $name = pterodactyl_GetOption($params, 'server_name', 'Server - ' . $params['serviceid']);
        $memory = pterodactyl_GetOption($params, 'memory');
        $swap = pterodactyl_GetOption($params, 'swap');
        $io = pterodactyl_GetOption($params, 'io');
        $cpu = pterodactyl_GetOption($params, 'cpu');
        $disk = pterodactyl_GetOption($params, 'disk');
        $pack_id = pterodactyl_GetOption($params, 'pack_id');
        $location_id = pterodactyl_GetOption($params, 'location_id');
        $dedicated_ip = pterodactyl_GetOption($params, 'dedicated_ip') ? true : false;
        $port_range = pterodactyl_GetOption($params, 'port_range');
        $port_range = isset($port_range) ? explode(',', $port_range) : [];
        $image = pterodactyl_GetOption($params, 'image', $eggData['attributes']['docker_image']);
        $startup = pterodactyl_GetOption($params, 'startup', $eggData['attributes']['startup']);
        $databases = pterodactyl_GetOption($params, 'databases');
        $allocations = pterodactyl_GetOption($params, 'allocations');
        $serverData = [
            'description' => 'Client: #' . $params['clientsdetails']->id_code . ' Service: #' . $params['serviceid'],
            'name' => $name,
            'user' => (int)$userId,
            'nest' => (int)$nestId,
            'egg' => (int)$eggId,
            'docker_image' => $image,
            'startup' => $startup,
            'limits' => [
                'memory' => (int)$memory,
                'swap' => (int)$swap,
                'io' => (int)$io,
                'cpu' => (int)$cpu,
                'disk' => (int)$disk,
            ],
            'feature_limits' => [
                'databases' => $databases ? (int)$databases : null,
                'allocations' => (int)$allocations,
            ],
            'deploy' => [
                'locations' => [(int)$location_id],
                'dedicated_ip' => $dedicated_ip,
                'port_range' => $port_range,
            ],
            'environment' => $environment,
            'start_on_completion' => true,
            'external_id' => (string)$params['serviceid'],
        ];
        if (isset($pack_id)) $serverData['pack'] = (int)$pack_id;


        $server = pterodactyl_API($params, 'servers', $serverData, 'POST');
        if ($server['status_code'] === 400) throw new Exception('Couldn\'t find any nodes satisfying the request.');
        if ($server['status_code'] !== 201) throw new Exception('Failed to create the server, received the error code: ' . $server['status_code'] . '. Enable module debug log for more info.');

        return $server;

    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterodactyl_SuspendAccount(array $params) {
    try {
        $serverId = pterodactyl_GetServerID($params);
        if (!isset($serverId)) throw new Exception('Failed to suspend server because it doesn\'t exist.');

        $suspendResult = pterodactyl_API($params, 'servers/' . $serverId . '/suspend', [], 'POST');
        if ($suspendResult['status_code'] !== 204) throw new Exception('Failed to suspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterodactyl_UnsuspendAccount(array $params) {
    try {
        $serverId = pterodactyl_GetServerID($params);
        if (!isset($serverId)) throw new Exception('Failed to unsuspend server because it doesn\'t exist.');

        $suspendResult = pterodactyl_API($params, 'servers/' . $serverId . '/unsuspend', [], 'POST');
        if ($suspendResult['status_code'] !== 204) throw new Exception('Failed to unsuspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterodactyl_TerminateAccount(array $params) {
    try {
        $serverId = pterodactyl_GetServerID($params);
        if (!isset($serverId)) throw new Exception('Failed to terminate server because it doesn\'t exist.');

        $deleteResult = pterodactyl_API($params, 'servers/' . $serverId, [], 'DELETE');
        if ($deleteResult['status_code'] !== 204) throw new Exception('Failed to terminate the server, received error code: ' . $deleteResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}
