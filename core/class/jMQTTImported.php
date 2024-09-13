<?php

declare(strict_types=1);

/**
 * Model class that hold jMQTT instance created alongside the data imported from a CSV file.
 */
final class jMQTTImported
{

    private $jMQTT;
    private $csvData;

    public function __construct(jMQTT $jMQTT, array $csvData)
    {
        $this->jMQTT = $jMQTT;
        $this->csvData = $csvData;
    }

    public function getJMQTT(): jMQTT
    {
        return $this->jMQTT;
    }

    public function getCsvData(): array
    {
        return $this->csvData;
    }

    public function getId(): ?string
    {
        return $this->csvData[$this->findBestColumnName('SN')] ?? null;
    }

    public function getDevEUI(): ?string
    {
        return $this->csvData[$this->findBestColumnName('DevEUI')] ?? null;
    }

    public function getJoinEUI(): ?string
    {
        // AppEUI is a alias for JoinEUI
        return $this->csvData[$this->findBestColumnName('AppEUI')] ?? null;
    }

    public function getAppKey(): ?string
    {
        return $this->csvData[$this->findBestColumnName('AppKey')] ?? null;
    }

    public function getName(): ?string
    {
        return $this->jMQTT->getName();
    }


    /**
     * Try to find the best column name in the CSV data that match the given column name.
     */
    private function findBestColumnName(string $columnNameToFind): ?string
    {
        $bestColumnName = null;
        $bestColumnNameSimilarity = 0;
        foreach (array_keys($this->csvData) as $columnName) {
            $similarity = similar_text(strtolower($columnNameToFind), strtolower($columnName));
            if ($similarity > $bestColumnNameSimilarity) {
                $bestColumnName = $columnName;
                $bestColumnNameSimilarity = $similarity;
            }
        }

        return $bestColumnName;
    }
}
