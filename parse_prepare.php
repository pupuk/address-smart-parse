<?php
/**
* 
*/
class Address
{

    /**
     * 地址智能解析
     * @param string 包含丰富信息的字符串
     * @return array 姓名，手机号，邮编，详细地址
     */
    public static function smart_parse($address)
    {   
        //解析结果
        $parse = [];
        $parse['name']     = '';
        $parse['mobile']   = '';
        $parse['postcode'] = '';
        $parse['detail']   = '';

        //1. 过滤掉收货地址中的常用说明字符，排除干扰词
        $search = ['地址', '收货地址', '收货人', '收件人', '收货', '邮编', '电话', '：', ':', '；', ';', '，', ',', '。', ];
        $replace = [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '];
        $address = str_replace($search, $replace, $address);

        //2. 连续2个或多个空格替换成一个空格
        $address = preg_replace('/ {2,}/', ' ', $address);

        //3. 去除手机号码中的短横线 如136-3333-6666 主要针对苹果手机
        $address = preg_replace('/(\d{3})-(\d{4})-(\d{4})/', '$1$2$3', $address);

        //4. 提取11位手机号码或者7位以上座机号
        preg_match('/\d{7,11}|\d{3,4}-\d{6,8}/', $address, $match);
        if ($match && $match[0]) {
            $parse['mobile'] = $match[0];
            $address = str_replace($match[0], '', $address);
        }

        //5. 提取6位邮编 邮编也可用后面解析出的省市区地址从数据库匹配出
        preg_match('/\d{6}/', $address, $match);
        if ($match && $match[0]) {
            $parse['postcode'] = $match[0];
            $address = str_replace($match[0], '', $address);
        }

        //再次把2个及其以上的空格合并成一个，并首位TRIM
        $address = trim(preg_replace('/ {2,}/', ' ', $address));

        //按照空格切分 长度长的为地址 短的为姓名 因为不是基于自然语言分析，所以采取统计学上高概率的方案
        $split_arr = explode(' ', $address);
        if (count($split_arr) > 1) {
            $parse['name'] = $split_arr[0];
            foreach ($split_arr as $value) {
                if (strlen($value) < strlen($parse['name'])) {
                    $parse['name'] = $value;
                }
            }
            $address = trim(str_replace($parse['name'], '', $address));
        }
        $parse['detail'] = $address;


        //parse['detail']详细地址可以传入另一个文件的函数，解析出：省，市，区，街道地址
        var_dump($parse);
    }
}

$obj = Address::smart_parse('收货人姓某某收货地址：武侯区倪家桥路11号附2号  617000  136-3333-6666 ');

//上面例子解析结果
array(4) {
  ["name"]=>
  string(9) "姓某某"
  ["mobile"]=>
  string(11) "13633336666"
  ["postcode"]=>
  string(6) "617000"
  ["detail"]=>
  string(33) "武侯区倪家桥路11号附2号"
}

