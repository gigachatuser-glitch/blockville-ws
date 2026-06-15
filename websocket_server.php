<?php
$host = '0.0.0.0';
$port = 8080;

$socket = stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

echo "WebSocket server running on $host:$port\n";
$clients = [];

while (true) {
    $read = $clients;
    $read[] = $socket;
    $write = null;
    $except = null;
    
    if (stream_select($read, $write, $except, 0, 10000) === false) break;
    
    if (in_array($socket, $read)) {
        $newClient = stream_socket_accept($socket);
        $clients[] = $newClient;
        
        $headers = fread($newClient, 1024);
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $matches)) {
            $key = $matches[1];
            $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                       "Upgrade: websocket\r\n" .
                       "Connection: Upgrade\r\n" .
                       "Sec-WebSocket-Accept: $accept\r\n\r\n";
            fwrite($newClient, $upgrade);
        }
        unset($read[array_search($socket, $read)]);
    }
    
    foreach ($read as $client) {
        $data = fread($client, 1024);
        if ($data === false || $data === '') {
            $key = array_search($client, $clients);
            unset($clients[$key]);
            continue;
        }
        
        $response = json_encode(['type' => 'pong', 'timestamp' => time() * 1000]);
        $len = strlen($response);
        fwrite($client, chr(129) . chr($len) . $response);
    }
}
?>
