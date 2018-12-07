<?php
/**
* 
*/
class AddressDetail
{
    /**
     * 地址智能解析 为了提现思路，注释较多，经过测试，识别率在95%左右
     * 非自然语言处理，但由于国家的地址省市区街道是有特征和统计规律的
     * 所以本程序才能产生识别效果，但还是要考虑特殊情况，如自治州，县级市等
     * 可使用本仓库的SQL地区库
     * @param string 收货地址 不含姓名手机号
     * @return array 省，市，区，街道地址
     */
    public static function detail_parse($detail)
    {
        // 测试例子
        // $detail = '成都市高新区天府软件园B区科技大楼';
        // $detail = '双流县郑通路社保局区52050号';
        // $detail = '岳市岳阳楼区南湖求索路碧灏花园A座1101';
        // $detail = '四川省南充市阆中市公园路25号';
        // $detail = '四川省阆中市公园路25号';
        // $detail = '四川省 凉山州美姑县xxx小区18号院';
        // $detail = '重庆攀枝花市东区机场路3中学校';
        // $detail = '渝北区渝北中学51200街道地址';
        // $detail = '天津天津市红桥区水木天成1区临湾路9-3-1101';
        
        $detail = str_replace(' ', '', $detail);

        //返回结果
        $result = [];

        /**
         * 1. 三级地址识别 共有2992个三级地址 高频词为【县，区，旗，市】是整个识别系统的关键
         * 返回 [%第3级% 模糊地址] [街道地址]
         * 三级地址 前面一般2或3个字符就够用了【3个字符，比如高新区，仁和区，武侯区，占比96%】【2个字符的县和区有140个左右，占比4%，比如理县】
         */
        
        if (mb_strstr($detail, '县') || mb_strstr($detail, '区') || mb_strstr($detail, '旗')) {
            // 如果同时出现 县和区 我们可以确定的是县一定在区前面，所以下面三个if顺序是有要求的，不能随便调整
            if (mb_strstr($detail, '旗')) {
                $deep3_keyword_pos = mb_strpos($detail, '旗');
                $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 1, 2);
            }

            if (mb_strstr($detail, '区')) {
                $deep3_keyword_pos = mb_strpos($detail, '区');

                // 判断区、市是同时存在 同时存在 可以简单 比如【攀枝花市东区攀枝花三中高三班2010级】
                if (mb_strstr($detail, '市')) {
                    $city_pos = mb_strpos($detail, '市');
                    $zone_pos = mb_strpos($detail, '区');
                    $deep3_area_name = mb_substr($detail, $city_pos + 1, $zone_pos - $city_pos);
                } else {
                    $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                    //县名称最大的概率为3个字符 美姑县 阆中市 高新区
                }
            }

            if (mb_strstr($detail, '县')) {
                $deep3_keyword_pos = mb_strpos($detail, '县');
                // 判断县市是同时存在 同时存在 可以简单 比如【湖南省常德市澧县】
                if (mb_strstr($detail, '市')) {
                    $city_pos = mb_strpos($detail, '市');
                    $zone_pos = mb_strpos($detail, '县');
                    $deep3_area_name = mb_substr($detail, $city_pos + 1, $zone_pos - $city_pos);
                } else {
                    $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                    //县名称最大的概率为3个字符 美姑县 阆中市 高新区
                }
            }

            $street = mb_substr($detail, $deep3_keyword_pos + 1);
        } else {
            if (mb_strripos($detail, '市')) {
                //最大的可能性为县级市 可能的情况有【四川省南充市阆中市公园路25号，四川省南充市阆中市公园路25号】市要找【最后一次】出现的位置
                $deep3_keyword_pos = mb_strripos($detail, '市');
                $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                $street = mb_substr($detail, $deep3_keyword_pos + 1);
            } else {
                //不能识别的解析
                $deep3_area_name = '';
                $street = $detail;
            }
        }

        /**
         * 2. 二级地址的识别 共有410个二级地址 高频词为【市，盟，州】 高频长度为3,4个字符 因为有用户可能会填写 '四川省阆中市'，所以二级地址的识别可靠性并不高 需要与三级地址 综合使用
         * 返回 [%第2级% 模糊地址]
         */
        if (mb_strrpos($detail, '市') || mb_strstr($detail, '盟') || mb_strstr($detail, '州')) {
            if ($tmp_pos = mb_strrpos($detail, '市')) {
                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
            }

            if ($tmp_pos = mb_strrpos($detail, '盟')) {
                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
            }

            if ($tmp_pos = mb_strrpos($detail, '州')) {
                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
            }
        } else {
            $deep2_area_name = '';
        }

        //3. 到数据中智能匹配
        if ($deep3_area_name != '') {

            //数据库匹配 以下的数据库匹配需要程序员根据自己的框架自行替换
            $condition = [];
            $condition['area_deep'] = 3;
            $condition['area_name'] = array('like', '%' . $deep3_area_name . '%');
            $deep3_area_list = $model_area->getAreaList($condition);

            // 三级地址的匹配出现多个结果 依靠二级地址缩小范围
            if ($deep3_area_list && count($deep3_area_list) > 1) {
                if ($deep2_area_name) {
                    $area_info_2 = $model_area->getAreaInfo(['like', '%' . $deep2_area_name . '%']);
                    //2级地址匹配成功 再次缩小三级地址 然后确定一级地址
                    if ($area_info_2) {
                        $area_info_3 = $model_area->getAreaInfo(['area_parent_id' => $area_info_2['area_id'], 'area_name' => array('like', '%' . $deep3_area_name . '%')]);
                    }
                    $area_info_1 = $model_area->getAreaInfo(['area_id' => $area_info_2['area_parent_id'], 'area_deep' => 1]);

                    //获得结果
                    $result[1]['area_id'] = $area_info_2['area_parent_id'];
                    $result[1]['area_name'] = $area_info_1['area_name'];
                    $result[2]['area_id'] = $area_info_2['area_id'];
                    $result[2]['area_name'] = $area_info_2['area_name'];
                    $result[3]['area_id'] = $area_info_3['area_id'];
                    $result[3]['area_name'] = $area_info_3['area_name'];
                }

            } else {
                if ($deep3_area_list && count($deep3_area_list) == 1) {
                    $area_info_2 = $model_area->getAreaInfo(['area_id' => $deep3_area_list[0]['area_parent_id'], 'area_deep' => 2]);

                    if ($area_info_2) {
                        $area_info_1 = $model_area->getAreaInfo(['area_id' => $area_info_2['area_parent_id'], 'area_deep' => 1]);

                        //获得结果
                        $result[1]['area_id'] = $area_info_2['area_parent_id'];
                        $result[1]['area_name'] = $area_info_1['area_name'];
                        $result[2]['area_id'] = $area_info_2['area_id'];
                        $result[2]['area_name'] = $area_info_2['area_name'];
                        $result[3]['area_id'] = $deep3_area_list[0]['area_id'];
                        $result[3]['area_name'] = $deep3_area_list[0]['area_name'];
                    }

                }
            }
        }

        //最终结果
        return $result;
    }
}

$obj = AddressDetail::detail_parse('成都市高新区天府软件园B区科技大楼');
$obj2 = AddressDetail::detail_parse('朝阳区宵云路36号国航大厦一层');
