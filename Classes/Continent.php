<?php
class Continent {
    private int $continentID;
    private string $continentName;

    public function __construct(int $continentID = 0, string $continentName = '') {
        $this->continentID = $continentID;
        $this->continentName = $continentName;
    }

    public function getContinentID(): int {
        return $this->continentID;
    }

    public function getContinentName(): string {
        return $this->continentName;
    }

    public function setContinentID(int $continentID): void {
        $this->continentID = $continentID;
    }

    public function setContinentName(string $continentName): void {
        $this->continentName = $continentName;
    }

    public function identifyContinent(string $ip, array $continentMap): ?int {
        $databasePath = __DIR__ . '/../geoip/GeoLite2-Country.mmdb';

        if (!file_exists($databasePath)) {
            return null;
        }

        try {
            $reader = new \GeoIp2\Database\Reader($databasePath);
            $record = $reader->country($ip);
            $continentName = $record->continent->name ?? null;

            $reader->close();

            if (!$continentName) {
                return null;
            }

            $continentName = trim($continentName);

            if ($continentName === 'Australia') {
                $continentName = 'Oceania';
            }

            return $continentMap[$continentName] ?? null;
        } 
        
        catch (\Throwable $e) {
            return null;
        }
    }
}