<?php

function bbtrace_name($file_name, $suffix)
{
    $file_name = realpath($file_name);
    $path_exe = dirname($file_name);
    $name_exe = basename($file_name);

    $name = $path_exe.DIRECTORY_SEPARATOR.'bbtrace.'.$name_exe;
    if (!is_null($suffix)) $name .= '.'. $suffix;
    return $name;
}
