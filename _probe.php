<?php
echo "ini=" . php_ini_loaded_file() . PHP_EOL;
echo "cainfo=[" . ini_get("curl.cainfo") . "]" . PHP_EOL;
echo "sapi=" . php_sapi_name() . PHP_EOL;
