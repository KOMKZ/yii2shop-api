<?php
/**
 * Created by PhpStorm.
 * User: lartik
 * Date: 18-9-9
 * Time: 下午11:13
 */
namespace lgoods\controllers;

use lgoods\models\goods\GoodsModel;
use lgoods\models\order\OrderModel;
use lgoods\models\trans\TransModel;
use Yii;
use lbase\Controller;




class LsaleRuleController extends Controller{
    /**
     * @api post,/lsale-rule,SaleRule,创建一条销售规则
     * - sr_name required,string,in_body,规则名称
     * - sr_caculate_type required,integer,in_body,规则计算模型
     * - sr_caculate_params required,string,in_body,规则计算模型参数
     * - sr_object_id required,integer,in_body,规则作用对象id
     * - sr_object_type required,integer,in_body,规则作用对象类型
     * - sr_start_at required,integer,in_body,开始时间
     * - sr_end_at required,integer,in_body,结束时间
     * - sr_usage_intro optional,string,in_body,使用说明
     *
     * @return #global_res
     * - data object#sale_rule_item,返回规则对象
     */
    public function actionCreate(){

    }

    /**
     * @api put,/lsale-rule/{id},SaleRule,创建一条销售规则
     * - id required,string,in_path,规则sr_id
     * - sr_name required,string,in_body,规则名称
     * - sr_caculate_params optional,string,in_body,规则计算模型参数
     * - sr_start_at optional,integer,in_body,开始时间
     * - sr_end_at optional,integer,in_body,结束时间
     * - sr_status optional,integer,in_body,状态
     * - sr_usage_intro optional,string,in_body,使用说明
     *
     * @return #global_res
     * - data object#sale_rule_item,返回规则对象
     */
    public function actionUpdate($index){

    }

    /**
     * @api get,/lsale-rule,SaleRule,查询销售规则
     * - sr_start_at optional,integer,in_query,开始时间
     * - sr_end_at optional,integer,in_query,结束时间
     * - sr_status optional,integer,in_query,状态
     * - sr_object_id optional,integer,in_query,规则作用对象id
     * - sr_object_type optional,integer,in_query,规则作用对象类型
     *
     * @return #global_res
     * - data object#sale_rule_item_list,返回规则对象列表
     */
    public function actionList(){

    }
}

/**
 * @def #sale_rule_item_list
 * - total_count integer,总数量
 * - items array#sale_rule_item,规则列表
 * 
 * @def #sale_rule_item
 * - sr_id integer,主键
 * - sr_name string,规则名称
 * - sr_caculate_type integer,规则计算模型
 * - sr_caculate_params integer,规则计算模型参数
 * - sr_object_id integer,规则作用对象id
 * - sr_object_type integer,规则作用对象类型
 * - sr_created_at integer,创建时间
 * - sr_updated_at integer,更新时间
 * - sr_start_at integer,开始时间
 * - sr_end_at integer,结束时间
 * - sr_status integer,状态
 * - sr_usage_intro string,使用说明
 *
 */