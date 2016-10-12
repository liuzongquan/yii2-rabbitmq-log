<?php
/**
 * Created by PhpStorm.
 * User: liuzongquan
 * Date: 16/9/13
 * Time: 14:39
 */

namespace yidu\rabbitmq\log;

use yidu\rabbitmq\components\Amqp;
use yii\helpers\VarDumper;
use yii\log\Target;
use yii\log\Logger;
use yii\base\InvalidConfigException;

/**
 * Class RabbitmqTarget
 * @package webtoucher\amqp\log
 * @author liuzongquan <zongquanliu2010@gmail.com>
 */

class RabbitmqTarget extends Target
{
    public $rabbitmq ;
    public function init(){
        parent::init();
        $this->rabbitmq= new Amqp();
        date_default_timezone_set("Asia/Hong_Kong");
    }

    /**
     * flatten array
     * @param array $array
     * @return array
     */

    function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a,$b) use (&$return) { $return[$b] = $a; });
        return $return;
    }

    function iterate_array(array $array){
        $str = "";
        foreach($array as $key=>$val){
            if(is_array($val)){
                $str=$str.$this->iterate_array($val);
            }else{
                $str=$str."['".$key."'='".$val."']";
            }
        }
        return $str;
    }

    /**
     * Formats a log message for display as a string.
     * @param array $message the log message to be formatted.
     * The message structure follows that in [[Logger::messages]].
     * @return string the formatted message
     */
    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            }
            else{
                if(is_array($text)){
                    $text = $this->iterate_array($text);
                }else{
                    $text = VarDumper::export($text);
                }
            }
        }else{
            //处理系统运行时日志
            $text = str_replace("\n","",$text,$count);
            $regex = "/('.*?'\s*\=\>\s*'.*?')/";
            $matches=array();
            preg_match_all($regex,$text,$matches,PREG_SET_ORDER);
            $count = count($matches);
            $tempText = "";
            for($i=0;$i<$count;$i++){
                $str = $matches[$i][0];
                $str = str_replace(" => ","=",$str);
                $tempText = $tempText."[".$str."]";
            }
            $text = $tempText;
        }
//        $traces = [];
//        if (isset($message[4])) {
//            foreach ($message[4] as $trace) {
//                $traces[] = "in {$trace['file']}:{$trace['line']}";
//            }
//        }
        $prefix = $this->getMessagePrefix($message);
//        return date('Y-m-d H:i:s', $timestamp) . " {$prefix}[$level][$category] $text";
//        . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
        $retStr = date('Y-m-d H:i:s', $timestamp) . " {$prefix}[$level][$category] $text";
        $messageInfo[$retStr] = $category;
        return $messageInfo;
    }

    public function export()
    {
        // TODO: Implement export() method.
        $messages = array_map([$this, 'formatMessage'], $this->messages);
        foreach ($messages as $messageInfo) {
            foreach ($messageInfo as $message=>$category){
                $this->rabbitmq->send($category,$category,$message);
            }
//            $this->rabbitmq->send("hello-exchange3","hello-exchange3",$message);
        }

    }
}
