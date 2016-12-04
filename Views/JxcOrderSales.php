<?php
/**
 * 销售订单.
 *
 */
include_once "../Templates/include.php";

use Jxc\Impl\Core\JxcConfig;
use Jxc\Impl\Dao\CustomerDao;
use Jxc\Impl\Dao\ProductDao;
use Jxc\Impl\Vo\VoProduct;

$dao = new CustomerDao(JxcConfig::$DB_Config);
$resultSet = $dao->selectCustomNameList();
$custom_list = array();
foreach ($resultSet as $k => $v) {
    $custom_list[] = array('id' => $k, 'text' => $v['ct_name']);
}
$pub_custom_list = json_encode($custom_list);
//

$productDao = new ProductDao(JxcConfig::$DB_Config);
$products = $productDao->selectAll();

$map = array();
$pdt_list = array();
foreach ($products as $k => $v) {
    if ($v instanceof VoProduct) {
//        $map[$v->pdt_id] = $v->voToW2ui();
        $map[$v->pdt_id] = $v;
        //
        $w2ValRecId = array('id' => $k, 'text' => $v->pdt_id);
        $pdt_list[] = $w2ValRecId;
        $map[$v->pdt_id]->pdt_id = $w2ValRecId;
    }
}
$jsonProducts = json_encode($map);
$pdt_list = json_encode($pdt_list);

?>
<!DOCTYPE html>
<html lang="zh-cn">
<body id="body">
</body>
<script>
    $(document).data("cache_pdt_info", <?=$jsonProducts?>);
    var cacheOfPdtInfo = $(document).data('cache_pdt_info');

    $(document).ready(function () {
        var content = $('#div_main_cnt').w2grid({
            name: 'div_main_cnt',
            header: '销售管理',
            multiSelect: true,
            columnGroups: [
                {caption: '产品', span: 2},
                {caption: '颜色', master: true},
                {caption: '尺码', span: 9},
                {caption: '标价', span: 2},
                {caption: '总计', span: 2}
            ],
            columns: [
                {field: 'pdt_id', caption: '编号', size: '7%', style: 'text-align:center', editable: {type: 'text'}},
                {field: 'pdt_name', caption: '名称', size: '10%', style: 'text-align:center'},
                {field: 'pdt_color', caption: '颜色', size: '80px', render: W2Util.renderJxcColorCell},
                <?php
                // {field: 'pdt_count_1', caption: '2XS', size: '5%', editable: {type: 'text'}, render: renderSizeField},
                    $array = array( '3XS', '2XS', 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL' );
                    foreach ($array as $k => $v) {
                        echo "{field: 'pdt_count_{$k}', caption: '{$v}', size: '5%', editable: {type: 'text'}, render: W2Util.renderJxcPdtSizeCell},";
                    }
                ?>
                {field: 'pdt_zk', caption: '折扣', size: '7%',
                    editable: {type: 'percent', min: 0, max: 100}, render: 'percent'},
                {field: 'pdt_price', caption: '单价', size: '7%', render: 'money:2', editable: {type: 'float'}},
                {field: 'pdt_total', caption: '总数量', size: '10%'},
                {field: 'total_rmb', caption: '总价', size: '10%', render: 'money:2'}
            ],
            show: {
                header: true,
                toolbar: true,
                toolbarAdd: true,
                toolbarDelete: true,
                lineNumbers: true,
                footer: true
            },
            toolbar: {
                items: [
                    {type: 'break'},
                    {
                        type: 'button', id: 'btn_save_sales_order', caption: '保存', icon: 'w2ui-icon-check',
                        onClick: function (event) {
                            console.log(event);
                            console.log(this);
                            var grid = w2ui['div_main_cnt'];
                            var pdt_id = w2GridCheckUniqueID(grid, 'pdt_id');
                            if (pdt_id) {
                                w2alert("[Error]货号[" + pdt_id + "]重复, 请重新输入.", "Error");
                                return;
                            }
                            if (grid.getChanges().length <= 0) {
                                w2alert("[Msg]数据没有变更，不需要保存.", "Message");
                                return;
                            }
                            w2confirm("是否确定提交?", "确认提示框")
                                .yes(function () {
//                                    grid.save();

                                    var postData ={
                                        'changes' : grid.getChanges(),
                                        'ct_id' : 1,
                                    };
                                    var ajaxOptions = {
                                        type     : 'POST',
                                        url      : 'Jxc/do.php?api=product&c=sell',
                                        data     : postData,
                                        dataType : 'JSON'
                                    };
                                    $.ajax(ajaxOptions)
                                        .done(function (data, status, xhr) {
                                            if (data.status != 'success') {
                                                w2alert(data.msg, "Error");
                                            } else {
                                                console.log(data);
                                            }
                                        })
                                        .fail(function (xhr, status, error) {

                                        });
                                });
                        }
                    }
                ]
            },
            onEditField: function (event) {
                console.log(event);
                var that = this;
                var column = that.columns[event.column];
                var record = that.records[event.index];
                if ((column.field == 'pdt_id')
                    || (record && record.pdt_id == '')) {
                    event.preventDefault();
                    var url = "Jxc/do.php?api=product&c=pdtW2gridRecords";
                    $.getJSON(url, null, function (data) {
                        if (data['status'] == 'success') {
                            console.log('popup_initialized');
//                            var pdtOptions = popupPdtOption(that, event.index, event.column, 'pop_w2grid_pdt', data['records']);
                            var pdtOptions = popupPdtOption(that, event.index, 0, 'pop_w2grid_pdt', data['records']);
                            PopupUtil.onPopupShow({
                                subOptions: pdtOptions
                            });
                        }
                    });
                }
            },
            onChange: function (event) {
                console.log(event);
                var that = this;
                var column = this.columns[event.column];
                var record = that.records[event.index];
                if (event.value_new == '') {
                    event.preventDefault();
                    return;
                }
                if (record['pdt_id'] == undefined || record['pdt_id'] == '') {
                    w2alert("[Error]请先输入货号.", "Error");
                    return;
                }
                console.log('xxyy');
                event.onComplete = function (evt2) {
                    var total = 0;
                    var price = 0.0;
                    var zk = 100;
                    var counts = [];
                    for (var e = 0; e < that.columns.length; e++) {
                        var col = that.columns[e];
                        var val = that.getCellValue(event.index, e, false);
                        if (col.field.indexOf('pdt_count_') >= 0) {
                            var tmpIndex = col.field.substr(10);
                            counts[tmpIndex] = (event.column == e) ? Number(event.value_new) : val;
                        } else if (col.field == 'pdt_zk') {
                            zk = (event.column == e) ? Number(event.value_new).toFixed(0) : val;
                            if (zk <= 0) zk = 100;
                        } else if (col.field == 'pdt_price') {
                            price = Number(val).toFixed(2);
                        }
                    }
                    counts.map(function (v) {
                        total += Number(v);
                    });
                    var total_rmb = Number((price * zk / 100)).toFixed(2) * total;

                    that.set(record['recid'], {
                        'pdt_total': total,
                        'total_rmb': total_rmb
                    });
                };
            },
            onAdd: w2GridOnAdd,
            onSave: w2GridOnSaveAndUpdate,
            onKeydown: w2GridOnKeyDown
        });
        w2uiEmptyColumn(content, 1);
        w2ui['layout'].content('main', content);
    });
</script>
</html>