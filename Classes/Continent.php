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

    public function identifyContinent(): string {
        return $this->continentName;
    }
}
