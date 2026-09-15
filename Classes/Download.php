<?php

class Download {
    private int $downloadID;
    private int $continentID;

    public function __construct(int $downloadID = 0, int $continentID = 0) {
        $this->downloadID = $downloadID;
        $this->continentID = $continentID;
    }

    public function getDownloadID(): int {
        return $this->downloadID;
    }

    public function getContinentID(): int {
        return $this->continentID;
    }

    public function setDownloadID(int $downloadID): void {
        $this->downloadID = $downloadID;
    }

    public function setContinentID(int $continentID): void {
        $this->continentID = $continentID;
    }

    public function recordDownload(): bool {
        return true;
    }

    public function countDownload(): int {
        return 1;
    }
}
