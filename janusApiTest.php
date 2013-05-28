<?php

ob_start();

$config = parse_ini_file(__DIR__ . DIRECTORY_SEPARATOR . "config.ini");

if (FALSE === $config) {
    die("unable to read configuration file");
}

$idpEntities = $config['idpEntities'];
$spEntities = $config['spEntities'];
$idpMetadataKeys = $config['idpMetadataKeys'];
$spMetadataKeys = $config['spMetadataKeys'];

$janus_base_url = $config['janus_base_url'];

// TEST FUNCTIONS
function getEntity(array $config, $entityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getEntity',
        'entityid' => $entityId
    );

    return restCall($config, $values);
}

function getSpList(array $config)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getSpList'
    );

    return restCall($config, $values);
}

function getIdpList(array $config)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getIdpList'
    );

    return restCall($config, $values);
}

function getAllowedIdps(array $config, $spEntityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getAllowedIdps',
        'spentityid' => $spEntityId
    );

    return restCall($config, $values);
}

function getAllowedSps(array $config, $idpEntityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getAllowedSps',
        'idpentityid' => $idpEntityId
    );

    return restCall($config, $values);
}

function isConnectionAllowed(array $config, $idpEntityId, $spEntityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getAllowedIdps',
        'idpentityid' => $idpEntityId,
        'spentityid' => $spEntityId
    );

    return restCall($config, $values);
}

function arp(array $config, $spEntityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'arp',
        'entityid' => $spEntityId
    );

    return restCall($config, $values);
}

function getMetadata(array $config, $entityId)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getMetadata',
        'entityid' => $entityId
    );

    return restCall($config, $values);
}

function getMetadataKey(array $config, $entityId, $key)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getMetadata',
        'entityid' => $entityId,
        'keys' => $key
    );

    return restCall($config, $values);
}

function getMetadataKeyArray(array $config, $entityId, array $keys)
{
    $values = array(
        'janus_key' => $config['janus_key'],
        'method' => 'getMetadata',
        'entityid' => $entityId,
        'keys' => implode(",", $keys)
    );

    return restCall($config, $values);
}

// HELPER FUNCTIONS
function restCall(array $config, array $values)
{
    global $janus_base_url;

    ksort($values);
    $concat_string = '';
    foreach ($values as $key => $value) {
        $concat_string .= $key . $value;
    }
    $prepend_secret = $config['secret'] . $concat_string;
    $hash_string = hash('sha512', $prepend_secret);
    $query = http_build_query($values);
    $requestURL = $janus_base_url . '?'.$query.'&janus_sig=' .$hash_string;

    return @file_get_contents($requestURL);
}

function compareKeyValue(array $a, array $b, $name = NULL)
{
    // look if all keys in $a are also in $b and compare value then
    $msg = '';
    foreach ($a as $k => $v) {
        if (is_int($k)) {
            // compare by value, not key
            if (!in_array($v, $b)) {
                $msg .= "\t-" . $v . PHP_EOL;
            }
        } elseif (!array_key_exists($k, $b)) {
            if (is_array($v)) {
                $msg .= "\t-" . $k . "=" . "[" . json_encode($v) . "]" . PHP_EOL;
            } else {
                $msg .= "\t-" . $k . "=" . $v . PHP_EOL;
            }
        } else {
            if (is_array($v)) {
                $msg .= compareKeyValue($v, $b[$k], $k);
            } else {
                if ($a[$k] !== $b[$k]) {
                    $msg .= "\t-" . $k . "=" . $v . PHP_EOL;
                    $msg .= "\t+" . $k . "=" . $b[$k] . PHP_EOL;
                }
            }
        }
    }
    // look for missing stuff that is in $b, but not in $a
    foreach ($b as $k => $v) {
        if (is_int($k)) {
            // compare by value, not key
            if (!in_array($v, $a)) {
                $msg .= "\t+" . $v . PHP_EOL;
            }
        } elseif (!array_key_exists($k, $a)) {
            // missing key
            if (is_array($v)) {
                $msg .= "\t+" . $k . "=" . "[" . json_encode($v) . "]" . PHP_EOL;
            } else {
                $msg .= "\t+" . $k . "=" . $v . PHP_EOL;
            }
        }
    }

    if (!empty($msg) && NULL !== $name) {
        $msg = "[" . $name . "]" . PHP_EOL . $msg;
    }

    return $msg;
}

function compareData($config, $name, $remoteContent, $item = NULL)
{
    if (NULL === $item) {
        $filePath = $config['output_dir'] . DIRECTORY_SEPARATOR . $name . ".json";
    } else {
        $filePath = $config['output_dir'] . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . hash("md5", $item) . ".json";
    }
    @mkdir(dirname($filePath), 0777, TRUE);
    $fileContent = @file_get_contents($filePath);
    if (FALSE === $fileContent) {
        // file did not exist yet, create new one
        file_put_contents($filePath, $remoteContent);
    } else {
        $fileContent = empty($fileContent) ? "{}" : $fileContent;
        $remoteContent = empty($remoteContent) ? "{}" : $remoteContent;

        $msg = compareKeyValue(json_decode($fileContent, TRUE), json_decode($remoteContent, TRUE));
        if (!empty($msg)) {
            if (NULL === $item) {
                echo "[WARNING] " . $name . " differs" . PHP_EOL;
                echo $msg;
            } else {
                echo "[WARNING] " . $name . " (" . $item . ") differs" . PHP_EOL;
                echo $msg;
            }
        }
    }
}

// RUNNING THE TESTS
foreach ($idpEntities as $i) {
    compareData($config, "getEntityIdp", getEntity($config, $i), $i);
    compareData($config, "getMetadataIdP", getMetadata($config, $i), $i);
    foreach ($idpMetadataKeys as $k) {
        compareData($config, "getMetadataKeyIdP", getMetadataKey($config, $i, $k), $i . ": " . $k);
    }
    compareData($config, "getMetadataKeyArrayIdp", getMetadataKeyArray($config, $i, $idpMetadataKeys), $i . "[" . implode(",", $idpMetadataKeys) . "]");
}

foreach ($spEntities as $s) {
    compareData($config, "getEntitySp", getEntity($config, $s), $s);
    compareData($config, "getMetadataSp", getMetadata($config, $s), $s);
    foreach ($spMetadataKeys as $k) {
        compareData($config, "getMetadataKeySp", getMetadataKey($config, $s, $k), $s . ": " . $k);
    }
    compareData($config, "getMetadataKeyArraySp", getMetadataKeyArray($config, $s, $spMetadataKeys), $s . "[" . implode(",", $spMetadataKeys) . "]");
}

compareData($config, "getSpList", getSpList($config));
compareData($config, "getIdpList", getIdpList($config));

foreach ($spEntities as $s) {
   compareData($config, "getAllowedIdps", getAllowedIdps($config, $s), $s);
}

foreach ($idpEntities as $i) {
   compareData($config, "getAllowedSps", getAllowedSps($config, $i), $i);
}

foreach ($idpEntities as $i) {
    foreach ($spEntities as $s) {
        compareData($config, "isConnectionAllowed", isConnectionAllowed($config, $i, $s), $i . ": " . $s);
    }
}

foreach ($spEntities as $s) {
    compareData($config, "arp", arp($config, $s), $s);
}

;

$output = ob_get_contents();
ob_end_clean();

if (!empty($output)) {
    echo $output;
    exit(1);
}
exit(0);
