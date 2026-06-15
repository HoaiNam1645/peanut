<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Http;

class PushFileJsonToBackblazeService
{
    protected $accountId;
    protected $appId;
    protected $applicationKey;
    protected $authToken;
    protected $apiUrl;

    /**
     * Constructor - Using Laravel Storage for B2
     */
    public function __construct()
    {
        // Using Laravel Storage disk 'b2' - no manual authorization needed
    }

    /**
     * Extract File ID from Google Drive URL
     */
    private function getFileId($link)
    {
        // TH1: Dạng /file/d/FILE_ID/
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $link, $matches)) {
            return $matches[1];
        }

        // TH2: Dạng ?id=FILE_ID
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $link, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * List all buckets
     */
    public function listBuckets()
    {
        $client = new Client();
        $url = $this->apiUrl . '/b2api/v2/b2_list_buckets';
        $headers = [
            'Authorization' => $this->authToken
        ];
        $data = [
            'accountId' => $this->accountId,
            'bucketName' => env('B2_BUCKET'),
        ];

        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'json' => $data
        ]);

        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody(), true);
        } else {
            throw new Exception("Failed to list buckets: " . $response->getBody());
        }
    }

    /**
     * Get Bucket ID by bucket name
     */
    public function getBucketIdByName($bucketName)
    {
        $buckets = $this->listBuckets();

        if (isset($buckets['buckets'])) {
            $filtered = array_filter($buckets['buckets'], fn($bucket) => $bucket['bucketName'] === $bucketName);
            $firstMatch = reset($filtered);
            return $firstMatch ? $firstMatch['bucketId'] : null;
        }

        throw new Exception("Bucket with name '$bucketName' not found.");
    }

    /**
     * Upload PES file from any URL to B2 with new filename
     * Supports: Google Drive, B2, HTTP/HTTPS URLs
     * 
     * @param string $sourceUrl Source URL (Google Drive, B2, or any HTTP URL)
     * @param string $fileName Target filename
     * @param string $bucket_name Bucket name (not used with Laravel Storage)
     * @return array ['fileName' => B2 URL, 'raw' => response data]
     */
    public function pushPesToBlaze($sourceUrl, $fileName, $bucket_name)
    {
        // Detect URL type and get download URL
        $downloadUrl = $this->getDownloadUrl($sourceUrl);

        // Download file
        $response = Http::timeout(120)->get($downloadUrl);
        if (!$response->ok()) {
            throw new \Exception("Cannot download file from URL! Status: " . $response->status() . " URL: " . $downloadUrl);
        }

        // Upload to B2 using Laravel Storage
        $path = "pes_files/{$fileName}";
        Storage::disk('b2')->put($path, $response->body(), 'public');

        // Get B2 public URL
        $b2Url = Storage::disk('b2')->url($path);

        Log::info('Uploaded PES to B2', [
            'source_url' => $sourceUrl,
            'b2_path' => $path,
            'b2_url' => $b2Url,
            'file_name' => $fileName
        ]);

        return [
            'fileName' => $b2Url,
            'raw' => [
                'sourceUrl' => $sourceUrl,
                'fileName' => $fileName,
                'b2Path' => $path,
                'storage' => 'b2'
            ]
        ];
    }

    /**
     * Get direct download URL based on source type
     */
    private function getDownloadUrl(string $sourceUrl): string
    {
        // Google Drive URL
        if (str_contains($sourceUrl, 'drive.google.com')) {
            $fileId = $this->getFileId($sourceUrl);
            if (!$fileId) {
                throw new \Exception("Invalid Google Drive URL: Cannot extract file ID");
            }
            return "https://drive.google.com/uc?export=download&id=" . $fileId;
        }

        // B2 URL (already direct download) or any HTTP/HTTPS URL
        if (str_starts_with($sourceUrl, 'http://') || str_starts_with($sourceUrl, 'https://')) {
            return $sourceUrl;
        }

        throw new \Exception("Unsupported URL format: " . $sourceUrl);
    }

    /**
     * Convert a Google Drive share/view link into a direct-download URL so it can
     * be fetched with a plain HTTP GET (the /view link returns an HTML preview page,
     * not the file). Non-Drive URLs are returned unchanged.
     */
    public function toDirectDownloadUrl(?string $url): ?string
    {
        if (!$url || !str_contains($url, 'drive.google.com')) {
            return $url;
        }

        $fileId = $this->getFileId($url);

        return $fileId
            ? "https://drive.google.com/uc?export=download&id=" . $fileId
            : $url;
    }

    // ... Các hàm khác trong file (listFileVersions, deleteFileVersion, etc.)
}
