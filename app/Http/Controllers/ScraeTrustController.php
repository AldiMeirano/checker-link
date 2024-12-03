<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GifterCheckerController extends Controller
{
    private $nawalaInfo = "C5yRxtdDUPhW7FUQoD5jL1DMjtXFIDuky4ISm7m4GsQ";
    // private $redirectCentralInfo = "vrBd5UtCxPuIyIUqEIQuVCYwWQdkE6zQa1IGXWSGfDy";

    private function loadUrlsFromFile($filePath)
    {
        try {
            if (!File::exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            return array_filter(array_map('trim', explode("\n", File::get($filePath))));
        } catch (\Exception $e) {
            Log::error("Error loading URLs from file. File path: {$filePath}, Error: {$e->getMessage()}");
            return [];
        }
    }

    private function sendLineNotify($message, $token)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->asForm()->post('https://notify-api.line.me/api/notify', [
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::error("Error sending LINE Notify. Message: {$message}, Response: " . $response->body());
            } else {
                Log::info("Message sent to LINE Notify successfully. Message: {$message}");
            }
        } catch (\Exception $e) {
            Log::error("Exception occurred while sending LINE Notify. Message: {$message}, Error: {$e->getMessage()}");
        }
    }

    public function gifterCheckerNawala(Request $request)
    {
        $filePaths = [
            '/gifters' => storage_path('app/gifters.txt'),
            '/reg' => storage_path('app/reg.txt'),
            '/victory' => storage_path('app/victory.txt'),
        ];

        $reffKeys = [
            '/gifters' => ['gifters168', 'gifters'],
            '/reg' => ['reg168', 'reg'],
            '/victory' => ['victory168', 'victory'],
        ];

        $cekPath = $filePaths[$request->path()] ?? null;
        $reff = $reffKeys[$request->path()][0] ?? null;
        $key = $reffKeys[$request->path()][1] ?? null;

        if (!$cekPath || !$reff || !$key) {
            $errorMessage = "Invalid path provided. Path: " . $request->path();
            Log::error($errorMessage);
            return response()->json(['error' => $errorMessage], 400);
        }

        $urls = $this->loadUrlsFromFile($cekPath);

        if (empty($urls)) {
            $errorMessage = "No URLs found in the file. File path: {$cekPath}";
            Log::error($errorMessage);
            return response()->json(['error' => $errorMessage], 400);
        }

        $finalResults = [];

        foreach ($urls as $url) {
            try {
                Log::info("Processing URL: {$url}");
                $response = Http::get($url);
                $redirects = $response->effectiveUri();

                $trustResponse = Http::get("https://trustpositif.smbgroup.io/welcome?domains={$redirects->getHost()}");
                $html = $trustResponse->body();

                preg_match_all('/<tr>.*?<td>(.*?)<\/td>.*?<td>(.*?)<\/td>/s', $html, $matches, PREG_SET_ORDER);

                $data = [];
                foreach ($matches as $match) {
                    $data[] = ['domain' => $match[1], 'status' => $match[2]];
                }

                foreach ($data as $item) {
                    if ($item['status'] === 'Ada') {
                        $message = "NAWALA DETECTED: URL {$url}, DOMAIN {$item['domain']}, STATUS {$item['status']}";
                        Log::info("Nawala detected for URL: {$url}, Domain: {$item['domain']}");
                        $this->sendLineNotify($message, $this->nawalaInfo);
                    }
                }

                $finalResults[] = [
                    'originalUrl' => $url,
                    'finalDomain' => $redirects->getHost(),
                    'redirectInfo' => $redirects,
                    'trustStatus' => $data,
                ];
            } catch (\Exception $e) {
                $errorDetails = "Error processing URL. URL: {$url}, Error: {$e->getMessage()}";
                Log::error($errorDetails);
                $finalResults[] = [
                    'originalUrl' => $url,
                    'finalDomain' => null,
                    'redirectInfo' => 'Error processing URL',
                    'trustStatus' => [],
                ];
            }
        }

        return response()->json(['results' => $finalResults]);
    }
}
