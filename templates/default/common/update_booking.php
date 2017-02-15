<?php
require_once("../../../common/lib.php");
require_once("../../../common/define.php");
$response = array("html" => "", "notices" => array(), "error" => "", "success" => "");

if(isset($db) && $db !== false){
        
    if(isset($_SESSION['book'])){
    
        if(isset($_SESSION['book']['amount_rooms'])){
            $_SESSION['book']['tax_services_amount'] = 0;
            $_SESSION['book']['amount_services'] = 0;
            $_SESSION['book']['duty_free_services'] = 0;
                             
            foreach($_SESSION['book']['taxes'] as $tax_id => $taxes)
                if(isset($taxes['services'])) unset($_SESSION['book']['taxes'][$tax_id]['services']);
        }
        $people = $_SESSION['book']['adults']+$_SESSION['book']['children'];
        $nights = $_SESSION['book']['nights'];

        $extra_services = array();
        $total_services = 0;
        $duty_free_services = 0;
        $taxes = array();
        
        if(isset($_POST['extra_services']) && count($_POST['extra_services']) > 0){

            $result_service = $db->query("SELECT * FROM pm_service WHERE id IN(".implode(",", $_POST['extra_services']).") AND checked = 1 AND lang = ".LANG_ID);
            if($result_service !== false){
                
                $tax_id = 0;
                $result_incl_tax = $db->prepare("SELECT * FROM pm_tax WHERE id = :tax_id AND checked = 1 AND value > 0 GROUP BY id ORDER BY rank LIMIT 1");
                $result_incl_tax->bindParam(":tax_id", $tax_id);
                
                $taxes_id = "";
                $result_tax = $db->prepare("SELECT * FROM pm_tax WHERE (FIND_IN_SET(id, :taxes_id) OR id = :tax_id) AND checked = 1 AND value > 0 GROUP BY id ORDER BY rank");
                $result_tax->bindParam(":taxes_id", $taxes_id);
                $result_tax->bindParam(":tax_id", $tax_id);
        
                foreach($result_service as $i => $row){
                    $id = $row['id'];
                    $type = $row['type'];
                    $title = $row['title'];
                    $price = $row['price'];
                    $tax_id = $row['id_tax'];
                    $taxes_id = $row['taxes'];
                    
                    $qty = 0;
                    $rate = 0;
                    if(strpos($type, "qty") !== false && isset($_POST['qty_service_'.$id])){
                        $qty = $_POST['qty_service_'.$id];
                        $rate = $qty;
                        if($type == "qty-night") $rate *= $nights;
                        if($type == "qty-person-night") $rate *= $nights*$people;
                    }else{
                        if($type == "person-night") $qty = $nights*$people;
                        if($type == "person") $qty = $people;
                        if($type == "night") $qty = $nights;
                        if($type == "package") $qty = 1;
                        $rate = $qty;
                    }

                    if($qty > 0){
                        $price = $rate*$price;
                        $total_services += $price;
                        $extra_services[$id]['title'] = $title;
                        $extra_services[$id]['qty'] = $qty;
                        $extra_services[$id]['price'] = $price;
                        
                        if(isset($_SESSION['book']['amount_rooms'])){
                            $duty_free = $price;
                            if($result_incl_tax->execute() !== false && $db->last_row_count() > 0){
                                $incl_tax = $result_incl_tax->fetch();
                                $duty_free = $price/($incl_tax['value']/100+1);
                            }
                    
                            $duty_free_services += $duty_free;
                            
                            if($result_tax->execute() !== false){
                                foreach($result_tax as $tax){
                                    if(!isset($taxes[$tax['id']])) $taxes[$tax['id']] = 0;
                                    $taxes[$tax['id']] += $duty_free*($tax['value']/100);
                                }
                            }
                        }
                    }
                }
            }
            if($total_services > 0){
                
                if(isset($_SESSION['book']['amount_rooms'])){
                    
                    foreach($taxes as $tax_id => $tax_amount){
                        $_SESSION['book']['tax_services_amount'] += $tax_amount;
                        $_SESSION['book']['taxes'][$tax_id]['services'] = $tax_amount;
                    }
                }
            }
        }
        if(isset($_SESSION['book']['amount_rooms'])){
                
            $_SESSION['book']['extra_services'] = $extra_services;
            $_SESSION['book']['amount_services'] = $total_services;
            $_SESSION['book']['duty_free_services'] = $duty_free_services;
            
            $_SESSION['book']['total'] = $_SESSION['book']['duty_free_rooms']+$_SESSION['book']['tax_rooms_amount']+$_SESSION['book']['tourist_tax']
                                        + $_SESSION['book']['duty_free_activities']+$_SESSION['book']['tax_activities_amount']
                                        + $_SESSION['book']['duty_free_services']+$_SESSION['book']['tax_services_amount'];
            
            $_SESSION['book']['down_payment'] = (ENABLE_DOWN_PAYMENT == 1 && DOWN_PAYMENT_RATE > 0) ? $_SESSION['book']['total']*DOWN_PAYMENT_RATE/100 : 0;
            
            $response['html'] .= "
            <div class=\"row\">
                <div class=\"col-xs-6\">
                    <h3>".$texts['TOTAL']." <small>(".$texts['INCL_TAX'].")</small></h3>
                </div>
                <div class=\"col-xs-6 lead text-right\">".formatPrice($_SESSION['book']['total']*CURRENCY_RATE)."</div>
            </div>";
            
            $tax_id = 0;
            $result_tax = $db->prepare("SELECT * FROM pm_tax WHERE id = :tax_id AND checked = 1 AND value > 0 AND lang = ".LANG_ID." ORDER BY rank");
            $result_tax->bindParam(":tax_id", $tax_id);
            foreach($_SESSION['book']['taxes'] as $tax_id => $taxes){
                $tax_amount = 0;
                foreach($taxes as $amount) $tax_amount += $amount;
                if($tax_amount > 0){
                    if($result_tax->execute() !== false && $db->last_row_count() > 0){
                        $row = $result_tax->fetch();
                        $response['html'] .= "
                        <div class=\"row\">
                            <div class=\"col-xs-6\">".$row['name']."</div>
                            <div class=\"col-xs-6 text-right\">".formatPrice($tax_amount*CURRENCY_RATE)."</div>
                        </div>";
                    }
                }
            }
        }
        echo json_encode($response);
    }
}
