<?php

function split_for_arrays($pattern, $str) {
    $arr = preg_split($pattern, $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    /* Если первый элемент в массиве не совпадает с разделителем,
     * значит там мусор и он удаляется
     */
    if (preg_match($pattern, $arr[0]) == 0) {
        array_shift($arr);
    }
    /*
     * Если размер массива не четное число,то в конец добавляем пустой,
     * элемент. 
     */
    if ((count($arr) % 2 != 0)) {
        array_push($arr, "");
    }
    $new_arr = [];
    /*
     * Склеиваем элементы массива,что бы получить отдельные блоки текста
     * и сохранить разделитель.
     */
    for ($i = 0; $i != count($arr); $i += 2) {
        array_push($new_arr, $arr[$i] . $arr[$i + 1]);
    }
    return $new_arr;
}

function get_interfaces($text_arr, $patterns) {
    $all_interfaces = [];
    foreach ($text_arr as $str) {
        $interface = [];
        foreach ($patterns as $key => $pattern) {
            $arr = [];
            preg_match($pattern, $str, $arr);
            $interface[$key] = $arr[1];
        }
        array_push($all_interfaces, $interface);
    }
    return $all_interfaces;
}

interface IParser {

    function parse($text);
}

class Parser_mikrotik implements IParser {

    private $pattern = "/(\d*[\sa-zA-Z]*\sname=\"[a-z0-9-]*\")/";
    private $patterns = [
        'int' => "/\sname=\"([a-z0-9-]*)\"/",
        'mac' => "/mac-address=([0-9A-Za-z:]*)/",
        'mtu' => "/mtu=([0-9]*)/",
        'type' => "/type=\"([a-zA-Z0-9-]*)\"/",
        'fast_path' => "/fast-path=([a-z]*)/",
        'l2mtu' => "/l2mtu=([0-9]*)/",
        'default-name' => "/default-name=\"([a-zA-Z0-9-]*)\"/",
        'max_l2mtu' => "/max-l2mtu=([0-9]*)/"];

    public function parse($text) {
        $text_arr = split_for_arrays($this->pattern, $text);
        return get_interfaces($text_arr, $this->patterns);
    }

}

class Parser_linux implements IParser {

    private $pattern = "/([a-zA-Z0-9]*\s+Link)/";
    private $patterns = [
        'addr' => "/inet addr:\s*([0-9.]*)/",
        'mask' => "/Mask:\s*([0-9.]*)/",
        'mac' => "/HWaddr\s*([a-zA-Z0-9:]*)/",
        'bcast' => "/Bcast:\s*([0-9.]*)/",
        'int' => "/([a-zA-Z0-9]*)\s+Link/",
        'mtu' => "/MTU:\s*(\d*)/",
        'queue' => "/txqueuelen:\s*(\d*)/"];

    public function parse($text) {
        $text_arr = split_for_arrays($this->pattern, $text);
        return get_interfaces($text_arr, $this->patterns);
    }
}

class Parser_allied implements IParser {

    private $pattern = "/([a-zA-Z0-9]*\sDynamic|[a-zA-Z0-9]*\s---\sNotset\s-\s-\s-|[a-zA-Z0-9]*\sStatic)/";

    public function parse($text) {
        $text = preg_replace("/(Not set)/", "Notset", $text);
        $text_arr = split_for_arrays($this->pattern, $text);
        $interfaces = [];
        foreach ($text_arr as $value) {
            $param_arr = preg_split("/\s/", $value);
            if ($param_arr[2] == "Notset") {
                $param_arr[2] = "";
            }
            if ($param_arr[13] == "Notset") {
                $param_arr[13] = "";
            }
            array_push($interfaces, [
                "int" => $param_arr[0],
                "addr" => $param_arr[2],
                "mac" => $param_arr[13],
                "mtu" => $param_arr[14]]);
        }

        return $interfaces;
    }

}

class Parser_bsd implements IParser {

    private $pattern = "/([a-z0-9]*:\sflags=[0-9]*)/";
    private $patterns = [
        'addr' => "/inet\s([\.0-9]*)/",
        'mask' => "/netmask\s([a-f0-9x]*)/",
        'mac' => "/lladdr\s([a-z0-9:]*)/",
        'bcast' => "/broadcast\s([0-9\.]*)/",
        'int' => "/([a-z0-9]*):\sflags=/",
        'mtu' => "/mtu\s([0-9]*)/"];

    public function parse($text) {
        $text_arr = split_for_arrays($this->pattern, $text);
        return get_interfaces($text_arr, $this->patterns);
    }

}
