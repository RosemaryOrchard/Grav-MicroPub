<?php
/**
 * Created by PhpStorm.
 * User: rosemaryorchard
 * Date: 28/09/2018
 * Time: 21:48
 */
require_once 'config.php';

if (!preg_match('/Bearer (.+)/', $_SERVER['HTTP_AUTHORIZATION'], $match)) {
    headerDie(403, 'No token sent');
}
$accessToken = $match[1];

/*if (!ctype_alnum($accessToken)) {
    headerDie(403, 'Invalid token sent' . "\n" . print_r($_SERVER, true));
}*/

$ch = curl_init('https://tokens.indieauth.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/json'
]);
$token = json_decode(curl_exec($ch), true);

$url = URL;

if (!$token['me'] || rtrim($token['me'], '/') !== rtrim(URL, '/')) {
    file_put_contents('token.txt', print_r($token, true));
    headerDie(403, 'Token is either invalid or does not match the URL.' . "\n" . rtrim($token['me'], '/') . "\n" . rtrim(URL, '/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['q']) && $_GET['q'] === 'config') {
        $mpDestinationOptions = [];
        foreach (DESTINATION as $key => $value) {
            $mpDestinationOptions[] = [
                'uid' => $key,
                'name' => $value['name'],

            ];
        }

        $returnArray = [];
        $returnArray['media-endpoint'] = rtrim(URL, '/') . '/' . 'media-endpoint.php';
        $returnArray['destination'] = $mpDestinationOptions;
        die(json_encode($returnArray, JSON_PRETTY_PRINT));
    }


} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //get JSON data as an array (recursively), and merge it with the POST data so most requests are handled correctly
    $postedData = $_POST;
    $input = file_get_contents('php://input');
    $input = json_decode(json_encode(json_decode($input)), true);
    if (is_array($input)) {
        $postedData = array_merge($_POST, $input);
    }
    file_put_contents('request.json', json_encode($postedData));
    $gravHeaderChanges = [
        'name' => 'title',
        'published' => 'publish_date',
        'updated' => 'date',
    ];
    $gravHeaderInclude = [
        'checkin',
        'photo'
    ];
    $gravHeaderExclude = [
        'mp-destination',
        'properties'
    ];
    $gravPostHeader = [];

    //header content
    foreach ($postedData as $key => $value) {
        if (array_key_exists($key, $gravHeaderChanges)) {
            $gravPostHeader[$gravHeaderChanges[$key]] = $value;
        } elseif ($key === 'properties') {
            foreach ($value as $objectKey => $objectValue) {
                $gravPostHeader[$objectKey] = $objectValue;
            }
        } elseif (!array_key_exists($key, $gravHeaderExclude)) {
            $gravPostHeader[$key] = $value;
        }
    }

    $mpDestinationOptions = DESTINATION;
    if (array_key_exists('mp-destination', $_POST) && array_key_exists($_POST['mp-destination'], $mpDestinationOptions)) {
        $chosenDestination = $mpDestinationOptions[$_POST['mp-destination']];
    } else {
        $chosenDestination = reset($mpDestinationOptions);
    }

    $postURL = $chosenDestination['url'];

    $folder = 'user' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $chosenDestination['folder'];

    if (array_key_exists('properties', $postedData)) {
        $postedData['properties'] = (array)$postedData['properties'];
        if (array_key_exists('content', $postedData['properties'])) {
            $content = $postedData['properties']['content'];
        } elseif (array_key_exists('summary', $postedData['properties'])) {
            $content = $postedData['properties']['summary'];
        }
    } elseif (isset($postedData['content'])) {
        $content = $postedData['content'];
    } elseif (isset($postedData['summary'])) {
        $content = $postedData['summary'];
    } else {
        $content = null;
    }

    if (is_array($content)) {
        if (array_key_exists(0, $content) && array_key_exists('html', $content[0])) {
            $content = $content[0]['html'];
        } else {
            $content = print_r($content, true);
        }
    }


    $gravPostHeader['type'] = null;

    ///Set post type
    if (isset($postedData['rsvp'])) {
        $gravPostHeader['type'] = 'RSVP';
    } else if (isset($postedData['in-reply-to'])) {
        $gravPostHeader['type'] = 'reply';
    } else if (isset($postedData['repost-of'])) {
        $gravPostHeader['type'] = 'repost';
    } else if (isset($postedData['like-of'])) {
        $gravPostHeader['type'] = 'like';
    } else if (isset($postedData['video'])) {
        $gravPostHeader['type'] = 'video';
    } else if (isset($postedData['photo'])) {
        $gravPostHeader['type'] = 'photo';
    } else if (isset($postedData['type'])) {
        $gravPostHeader['type'] = $postedData['type'];
    } else {
        $gravPostHeader['type'] = 'note';
    }

    //Handle categories
    if (array_key_exists('properties', $postedData) && array_key_exists('category', $postedData['properties'])) {
        $gravPostHeader['taxonomy'][TAXONOMY] = $postedData['properties']['category'];
    }
    if (array_key_exists('category', $postedData)) {
        $gravPostHeader['taxonomy'][TAXONOMY] = $postedData['category'];
    }

    //change dashes in headers to underscores for YAML friendliness
    foreach ($gravPostHeader as $key => $value) {
        if (strpos($key, '-')) {
            $gravPostHeader[str_replace('-', '_', $key)] = $value;
            unset($gravPostHeader['$key']);
        }
    }

    $gravPost = str_replace('...', '---', yaml_emit($gravPostHeader));
    $gravPost .= $content;

    $slug = $postedData['slug'] ?? date('Y-m-d-Hi');
    if (strpos($slug, '/') !== false || strpos($slug, '..') !== false) {
        headerDie(403, 'Slug not valid');
    }

    $res = fileForceContents(rtrim($folder, '/') . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'post.md', $gravPost);
    if ($res) {
        //return success
        $postURL .= $slug;
        header('Location: ' . $postURL);
        headerDie(201, $gravPost);
    }
    file_put_contents('error.txt', date('H:i') . "\n" . print_r($res, true) . "\n" . $folder . "\n" . $slug);
    headerDie('500', 'Post could not be saved');
}

/*
 * Functions
 */

function headerDie($code, $message = null, $postData = null)
{
    switch ($code) {
        case 201:
            //header('HTTP/1.1 100 Content available below, please check');
            header('HTTP/1.1 201 Created');
            break;
        case 403:
            header('HTTP/1.1 403 Forbidden');
            break;
        case 500:
            header('HTTP/1.1 500 Internal Server Error');
            break;
        default:
            header('HTTP/1.1 501 Not Implemented');
            break;
    }
    $return = $message . $postData;
    if ($message && $postData) {
        $return = $message . "\n\n" . var_dump($postData);
    }
    die($return);
}

function fileForceContents($dir, $contents)
{
    $parts = explode(DIRECTORY_SEPARATOR, $dir);
    $file = array_pop($parts);
    $dir = __DIR__;
    foreach ($parts as $part) {
        if (!is_dir($dir .= DIRECTORY_SEPARATOR . $part)) {
            mkdir($dir);
        }
    }
    return file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $contents);
}