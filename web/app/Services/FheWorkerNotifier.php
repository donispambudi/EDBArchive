<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FheWorkerNotifier
{
    private const COMMAND = "WAKE\n";

    public function wake(): bool
    {
        $host = (string) config('edbarchive.worker.host', '127.0.0.1');
        $port = (int) config('edbarchive.worker.port', 9100);
        $timeout = max((float) config('edbarchive.worker.timeout', 1.0), 0.1);
        $errorCode = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            Log::warning('Unable to connect to the EDBArchive worker.', [
                'host' => $host,
                'port' => $port,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            return false;
        }

        try {
            $seconds = (int) $timeout;
            $microseconds = (int) (($timeout - $seconds) * 1_000_000);
            stream_set_timeout($socket, $seconds, $microseconds);

            $bytesWritten = fwrite($socket, self::COMMAND);
            $response = $bytesWritten === strlen(self::COMMAND)
                ? fgets($socket)
                : false;

            if ($response === false || trim($response) !== 'OK') {
                Log::warning('The EDBArchive worker did not acknowledge the WAKE command.', [
                    'host' => $host,
                    'port' => $port,
                    'response' => $response === false ? null : trim($response),
                ]);

                return false;
            }

            return true;
        } finally {
            fclose($socket);
        }
    }
}
