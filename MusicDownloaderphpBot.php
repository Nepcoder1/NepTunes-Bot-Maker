<?php
$botToken = 'YOUR_BOTTOKEN_HERE'; 
$telegramAPI = 'https://api.telegram.org/bot' . $botToken . '/';

// note replace All My Channel Username With Yours

function isMember($chatID, $user_id) {
    global $telegramAPI;
    $check = json_decode(file_get_contents($telegramAPI . 'getChatMember?chat_id=@devsnp&user_id=' . $user_id), true);//replace All My Channel Username With Yours
    return ($check['ok'] && ($check['result']['status'] === 'member' || $check['result']['status'] === 'administrator' || $check['result']['status'] === 'creator'));
}

function getSongDetails($songName) {
    $query = urlencode($songName);
    $url = "https://music.apinepdev.workers.dev/?song=$query&page=1&limit=1";

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data && $data['status'] === "SUCCESS" && isset($data['data']['results'][0])) {
        return $data['data']['results'][0];
    } else {
        return null;
    }
}

function downloadFile($url, $filePath) {
    $file = fopen($filePath, 'w');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $file);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($file);
}

$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message']['text'];
$chatID = $update['message']['chat']['id'];
$userID = $update['message']['from']['id'];

if (strpos($message, '/start') !== false) {
    if (isMember($chatID, $userID)) {
        $response = "Welcome! Please enter the name of the song you want to download.";
        file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($response));

       
        $usersFile = 'users.json';
        $existingUsers = [];

        if (file_exists($usersFile)) {
            $existingUsers = json_decode(file_get_contents($usersFile), true);
        }

        if (!in_array($userID, $existingUsers)) {
            $existingUsers[] = $userID;
            file_put_contents($usersFile, json_encode($existingUsers));
        }
    } else {
        $joinMessage = "To access music downloads, join our channel: https://t.me/devsnp ";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Join @devsnp', 'url' => 'https://t.me/devsnp']
                ]
            ]
        ];
        $encodedKeyboard = json_encode($keyboard);
        $replyMarkup = '&reply_markup=' . urlencode($encodedKeyboard);

        file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($joinMessage) . $replyMarkup);
    }
} else {
    if (isMember($chatID, $userID)) {
        $songDetails = getSongDetails($message);

        if ($songDetails) {
            $songName = $songDetails['name'];
            $artist = $songDetails['primaryArtists'];

            $downloadUrl = $songDetails['downloadUrl'][4]['link']; 

            if (strpos($downloadUrl, 'http') === 0) {
                file_get_contents($telegramAPI . 'sendChatAction?chat_id=' . $chatID . '&action=upload_document');

                $tempFilePath = 'song.mp3';
                downloadFile($downloadUrl, $tempFilePath);

                $caption = "Song: $songName\nArtist: $artist\nMusic downloaded by @NepMusicDownloderbot";

                $postFields = [
                    'chat_id' => $chatID,
                    'document' => new CURLFile($tempFilePath),
                    'caption' => $caption,
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => 'Join @devsnp', 'url' => 'https://t.me/devsnp']]]])
                ];
                $url = "https://api.telegram.org/bot{$GLOBALS['botToken']}/sendDocument";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_exec($ch);
                curl_close($ch);

                unlink($tempFilePath); 
            } else {
                $response = "Sorry, couldn't find the song. Please try again. ";
                file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($response));
            }
        } else {
            $response = "Sorry, couldn't find the song details. Please try again. ";
            file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($response));
        }
    } else {
        $joinMessage = "To access music downloads, join our channel: https://t.me/devsnp ";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Join @devsnp', 'url' => 'https://t.me/devsnp']
                ]
            ]
        ];
        $encodedKeyboard = json_encode($keyboard);
        $replyMarkup = '&reply_markup=' . urlencode($encodedKeyboard);

        file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($joinMessage) . $replyMarkup);
    }
}


$webhookResponse = file_get_contents($telegramAPI . 'setWebhook?url=REPLACEWITHYOURHOSTED WEBHOOK URL'); //put your webhook url

if ($webhookResponse) {
    echo "Webhook set successfully!";
} else {
    echo "Error setting webhook!";
}
?>
