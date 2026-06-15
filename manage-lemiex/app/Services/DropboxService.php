<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Reverb\Loggers\Log;

class DropboxService
{
    protected string $dropboxTempPath = '/dropbox/';
    protected string $accessToken;

    public function __construct()
    {
        //get from public/key_driver/tokendropbox.txt
        $this->accessToken = file_get_contents(public_path('key_driver/tokendropbox.txt'));
    }

    /**
     * @param  string  $fileName
     * @return bool
     */
    public function changeAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        //save to public/key_driver/tokendropbox.txt
        file_put_contents(public_path('key_driver/tokendropbox.txt'), $accessToken);
    }
    public function checkFileExist(string $fileName): bool
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ];

        $response = Http::withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/get_metadata', [
                'path' => $fileName,
                'include_media_info' => false,
                'include_deleted' => false,
                'include_has_explicit_shared_members' => false
            ]);

        return $response->successful();
    }

    /**
     * @param  string  $fileName
     * @return bool
     */
    public function deleteFileByName(string $fileName): bool
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ];

        $response = Http::withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/delete_v2', [
                'path' => $fileName
            ]);

        return $response->successful();
    }

    /**
     * @param  string  $fileUrl
     * @param  string  $destinationPath
     * @return bool
     */
    public function uploadFileFromUrl(string $fileUrl, string $destinationPath): bool|string
    {
        // Initialize cURL session
        $ch = curl_init($fileUrl);

        // Set options for cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Execute cURL request to fetch file content
        $fileContent = curl_exec($ch);

        // Check if cURL request was successful
        if ($fileContent === false) {
            // Close cURL session
            curl_close($ch);
            return "Failed to fetch file from URL";
        }

        // Close cURL session
        curl_close($ch);


        $fileLocalPath = $this->dropboxTempPath . basename($fileUrl);
        if (Storage::disk('local')->put($fileLocalPath, $fileContent) === false) {
            return "Failed to save file locally";
        }

        $path = Storage::disk('local')->path($fileLocalPath);
        $fp = fopen($path, 'rb');
        $size = filesize($path);
        // \Log::info("token".$this->accessToken);
        $headers = array(
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: {"path":"' . $destinationPath . '", "mode":"add"}'
        );

        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        // if ($response === false) {
        //     $error = curl_error($ch);
        //     curl_close($ch);
        //     fclose($fp);
        //     Storage::disk('local')->delete($fileLocalPath);
        //     return "cURL error: $error";
        // }

        curl_close($ch);
        fclose($fp);
        Storage::disk('local')->delete($fileLocalPath);

        return $response;
    }
    public function listFolder(string $path = "")
    {
        $response = Http::withToken($this->accessToken)
            ->post('https://api.dropboxapi.com/2/files/list_folder', [
                'path' => $path === "" ? "" : "/" . ltrim($path, "/"),
                'recursive' => false
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox list_folder failed: " . $response->body());
        }

        return $response->json();
    }

    public function refresh_token_request(): ?array
    {
        $ch = curl_init('https://api.dropbox.com/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'refresh_token',
            // 'refresh_token' => "6wvmfZyDG18AAAAAAAAAAeDKthXBJJ3CPSpfh24uEoAIKk1jdXI0xnvYuVvB-2KN",
            // 'client_id' => "00on8xaku9bp1dy",
            // 'client_secret' => "1zyf318mdi5kh10"
            'refresh_token' => config('services.dropbox.refresh_token'),
            'client_id' => config('services.dropbox.app_key'),
            'client_secret' => config('services.dropbox.app_secret')
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'Curl Error: ' . $error];
        }

        $responseData = json_decode($response, true);
        if ($responseData === null) {
            curl_close($ch);
            return ['error' => 'Invalid JSON Response: ' . $response];
        }

        curl_close($ch);

        return $responseData;
    }
    /**
     * Di chuyển tệp từ đường dẫn cũ đến đường dẫn mới trên Dropbox.
     *
     * @param  string  $fromPath  Đường dẫn nguồn trên Dropbox.
     * @param  string  $toPath    Đường dẫn đích trên Dropbox.
     * @return bool|string        Trả về `true` nếu di chuyển thành công, hoặc thông báo lỗi nếu không thành công.
     */
    public function moveFile(string $fromPath, string $toPath)
    {
        Log::info('Move file: ' . $fromPath . ' to ' . $toPath);
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'from_path' => $fromPath,
            'to_path' => $toPath,
            'allow_ownership_transfer' => true,
            'allow_shared_folder' => true,
            'autorename' => true
        ];

        $response = Http::withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/move_v2', $payload);

        if ($response->successful()) {
            return true;
        } else {
            return 'Failed to move file: ' . $response->body();
        }
    }
}
