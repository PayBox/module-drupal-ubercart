<?php
spl_autoload_register(function ($class) {
    $arr = explode('\\', $class);
    require implode(DIRECTORY_SEPARATOR, $arr).'.php';
});
