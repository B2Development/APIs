<?php

class CSV {

    const EMPTY_FIELD = "n/a";

    public function toCSV($header, $data)
    {
        $s = "";
        // get header
        foreach ($header as $key => $value) {
            if (!is_array($value)) {
                $s .= $this->encapsulateCSV($key) . ',';
            }
        }
        $s .= "\n";
        // get data
        foreach ($data as $row) {
            // Export the data as per the header column definitions.
            foreach ($header as $headerKey => $headerValue) {
                // set the value based on the header, or to the empty field.
                $value = isset($row[$headerKey]) ? $row[$headerKey] :
                    (isset($row[$headerValue]) ? $row[$headerValue] : self::EMPTY_FIELD);

                if (!is_array($value)) {
                    $s .= $this->encapsulateCSV(strval($value)) . ',';
                } else {
                    $s .= "\n" . $this->toCSV($value[0], $value) . "\n";
                }
            }
            $s .= "\n";
        }
        return $s;
    }

    private function encapsulateCSV($data)
    {
        $s = "";
        if ($data != "") {
            // handle quotes within fields.
            $s = str_replace('"', '""', $data);
            //$s = $data;
        }
        return '"' . $s . '"';
    }
}

?>
