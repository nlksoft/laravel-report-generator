<?php

namespace Jimmyjs\ReportGenerator\ReportMedia;

use App, Closure, Exception;
use League\Csv\Writer;
use Jimmyjs\ReportGenerator\ReportGenerator;

class CSVReport extends ReportGenerator
{
    private $showMeta = false;
    private $showHeader = true;

    public function showHeader($value = true)
    {
        $this->showHeader = $value;

        return $this;
    }

    public function showMeta($value = true)
    {
        $this->showMeta = $value;

        return $this;
    }

    public function download($filename)
    {
        if (!class_exists(Writer::class)) {
            throw new Exception('Please install league/csv to generate CSV Report!');
        }

        $csv = Writer::createFromFileObject(new \SplTempFileObject());

        if ($this->showMeta) {
            foreach ($this->headers['meta'] as $key => $value) {
                $csv->insertOne([$key, $value]);
                $csv->insertOne([]);
            }
        }

        $ctr = 1;
        $chunkRecordCount = ($this->limit == null || $this->limit > 5000) ? 5000 : $this->limit + 1;

        if ($this->showHeader) {
            $columns = array_keys($this->columns);
            $csv->insertOne($columns);
        }

        $this->query->chunk($chunkRecordCount, function($results) use(&$ctr, $csv) {
            foreach ($results as $result) {
                if ($this->limit != null && $ctr == $this->limit + 1) return false;
                if ($this->inRightOrder) {
                    $csv->insertOne($result->toArray());
                } else {
                    $formattedRows = $this->formatRow($result);
                    array_unshift($formattedRows, $ctr);
                    $csv->insertOne($formattedRows);
                }
                $ctr++;
            }
        });

        $csv->output($filename . '.csv');
    }

    private function formatRow($result)
    {
        $rows = [];
        foreach ($this->columns as $colName => $colData) {
            if (is_object($colData) && $colData instanceof Closure) {
                $generatedColData = $colData($result);
            } else {
                $generatedColData = $result->$colData;
            }
            $displayedColValue = $generatedColData;
            if (array_key_exists($colName, $this->editColumns)) {
                if (isset($this->editColumns[$colName]['displayAs'])) {
                    $displayAs = $this->editColumns[$colName]['displayAs'];
                    if (is_object($displayAs) && $displayAs instanceof Closure) {
                        $displayedColValue = $displayAs($result);
                    } elseif (!(is_object($displayAs) && $displayAs instanceof Closure)) {
                        $displayedColValue = $displayAs;
                    }
                }
            }

            array_push($rows, $displayedColValue);
        }

        return $rows;
    }
}