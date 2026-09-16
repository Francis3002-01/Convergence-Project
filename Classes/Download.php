<?php

class Download {
    private int $downloadID;
    private int $continentID;
    private int $journalID;

    public function __construct(int $downloadID = 0,int $continentID = 0,int $journalID = 0) {
        $this->downloadID = $downloadID;
        $this->continentID = $continentID;
        $this->journalID = $journalID;
    }

    public function getDownloadID(): int {
        return $this->downloadID;
    }

    public function getContinentID(): int {
        return $this->continentID;
    }

    public function getJournalID(): int {
        return $this->journalID;
    }

    public function setDownloadID(int $downloadID): void {
        $this->downloadID = $downloadID;
    }

    public function setContinentID(int $continentID): void {
        $this->continentID = $continentID;
    }

    public function setJournalID(int $journalID): void {
        $this->journalID = $journalID;
    }

    public function recordDownload(string $supabaseUrl, string $supabaseKey): array {
        return $this->supabaseRequest(
            'POST',
            'Download',
            [
                'journalID' => $this->journalID,
                'continentID' => $this->continentID
            ],
            $supabaseUrl,
            $supabaseKey
        );
    }

    public function countDownload(string $supabaseUrl, string $supabaseKey): int {
        $downloads = $this->supabaseRequest(
            'GET',
            'Download?select=downloadID',
            [],
            $supabaseUrl,
            $supabaseKey
        );

        return count($downloads);
    }

    private function supabaseRequest(string $method,string $endpoint,array $payload,string $supabaseUrl,string $supabaseKey): array {
        $url = rtrim($supabaseUrl, '/') . '/rest/v1/' . $endpoint;

        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if ($method !== 'GET') {
            $headers[] = 'Prefer: return=representation';
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15
        ]);

        if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $jsonPayload = json_encode($payload);

            if ($jsonPayload === false) {
                curl_close($ch);
                throw new RuntimeException('Unable to encode request data as JSON.');
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Supabase HTTP ' . $httpCode . ': ' . $response);
        }

        if (trim($response) === '') {
            return [];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid Supabase response: ' . $response);
        }

        return $decoded;
    }
}
