<?php
/**
 * Created by PhpStorm.
 * User: lartik
 * Date: 18-11-22
 * Time: 下午9:53
 */
namespace lgoods\models\sale\caculators;

class FullSub{
    public function check($priceItems){
        return true;
    }
    public function caculate($priceItems){
        list($price, $discount) = explode(',', $priceItems['sr_caculate_params']);
        return $discount;
    }
}