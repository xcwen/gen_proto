<?php

namespace App;

class Utils
{

    public static function exec_cmd($cmd)
    {
        #echo "DO exec : $cmd \n";
        $fp  = popen("$cmd", "r");
        $ret = "";
        while (!feof($fp)) {
            $ret .=fread($fp, 1024);
        }
        fclose($fp);
        return $ret;
    }

    public static function save_file($out_file_name, $data)
    {
        $old_data=@file_get_contents($out_file_name);
        if ($old_data != $data) {
            file_put_contents($out_file_name, $data);
            echo "update $out_file_name ....\n";
            return true;
        }
        return false;
    }

    public static function reset_map($map)
    {
        $ret_map=[];
        $key_list= array_keys($map);
        sort($key_list);
        foreach ($key_list as $key) {
            $ret_map[$key]=$map[$key];
        }
        return $ret_map;
    }
    //驼峰命名转下划线命名
    public static function str_to_under_score($str)
    {
        $dstr = preg_replace_callback('/([A-Z]+)/', function ($matchs) {
            return '_'.strtolower($matchs[0]);
        }, $str);
        return trim(preg_replace('/_{2,}/', '_', $dstr), '_');
    }


    //下划线命名到驼峰命名
    public static function toCamelCase($str)
    {
        $array = explode('_', $str);
        $result=""; //= $array[0];
        $len=count($array);
        if ($len>0) {
            for ($i=0; $i<$len; $i++) {
                $result.= ucfirst($array[$i]);
            }
        }
        return $result;
    }
}
