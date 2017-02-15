<?php
require_once("../../../common/lib.php");
require_once("../../../common/define.php");

if(isset($_POST['id']) && isset($_SESSION['user']['id'])){
    $id_booking = (int)$_POST['id'];
    if(is_numeric($id_booking)){ ?>
        <script>
            function printElem(elem){
                var popup = window.open('', 'print', 'height=800,width=600');
                popup.document.write('<html><head><title>'+document.title+'</title><link rel="stylesheet" href="<?php echo getFromTemplate("css/print.css"); ?>"/></head><body>'+document.getElementById(elem).innerHTML+'</body></html>');
                setInterval(function(){ 
                    popup.document.close();
                    popup.focus();
                    popup.print();
                    popup.close();    
                }, 100);
                return true;
            }
        </script>
        <div class="white-popup-block" id="popup-booking-<?php echo $id_booking; ?>">
            <?php
            $result_booking = $db->query("SELECT * FROM pm_booking WHERE id = ".$id_booking." AND id_user = ".$db->quote($_SESSION['user']['id']));
            if($result_booking !== false && $db->last_row_count() > 0){
                
                $row = $result_booking->fetch();
            
                echo "
                <h2>".$texts['BOOKING_SUMMARY']."</h2>
                <a href=\"#\" onclick=\"javascript:printElem('popup-booking-".$id_booking."');return false;\" class=\"pull-right print-btn\"><i class=\"fa fa-print\"></i></a>
                <p><strong>".$texts['BILLING_ADDRESS']."</strong><br>
                ".$row['firstname']." ".$row['lastname']."<br>";
                if($row['company'] != "") echo $texts['COMPANY']." : ".$row['company']."<br>";
                echo nl2br($row['address'])."<br>
                ".$row['postcode']." ".$row['city']."<br>
                ".$texts['PHONE']." : ".$row['phone']."<br>";
                if($row['mobile'] != "") echo $texts['MOBILE']." : ".$row['mobile']."<br>";
                echo $texts['EMAIL']." : ".$row['email']."</p>
                
                <p>".$texts['ROOM']." : <strong>".$row['room']."</strong><br>
                ".$texts['CHECK_IN']." <strong>".strftime(DATE_FORMAT, $row['from_date'])."</strong><br>
                ".$texts['CHECK_OUT']." <strong>".strftime(DATE_FORMAT, $row['to_date'])."</strong><br>
                <strong>".$row['nights']."</strong> ".$texts['NIGHTS']."<br>
                <strong>".($row['adults']+$row['children'])."</strong> ".$texts['PERSONS']." - 
                ".$texts['ADULTS'].": <strong>".$row['adults']."</strong> / 
                ".$texts['CHILDREN'].": <strong>".$row['children']."</strong><br>
                ".$texts['AMOUNT'].": ".formatPrice($row['amount']*CURRENCY_RATE)." ".$texts['INCL_VAT']."</p>";

                if($row['extra_services'] != ""){
                    $extra_services = explode("|", $row['extra_services']);
                    if(is_array($extra_services) && !empty($extra_services)){
                            
                        echo "<p><strong>".$texts['EXTRA_SERVICES']."</strong><br>";
                        foreach($extra_services as $extra){
                            $extra = explode(";", $extra);
                            echo $extra[0]." x ".$extra[1]." : ".formatPrice($extra[2]*CURRENCY_RATE)." ".$texts['INCL_VAT']."<br>";
                        }
                        echo "</p>";
                    }
                }

                if(ENABLE_TOURIST_TAX == 1 && $row['tourist_tax'] > 0) echo "<p>".$texts['TOURIST_TAX']." : ".formatPrice($row['tourist_tax']*CURRENCY_RATE)."</p>";
                
                $result_activity = $db->query("SELECT * FROM pm_booking_activity WHERE id_booking = ".$row['id']);
                if($result_activity !== false && $db->last_row_count() > 0){
                    echo "<p><b>".$texts['ACTIVITIES']."</b></p>";
                    foreach($result_activity as $activity){
                        echo "<p><b>".$activity['title']."</b> - ".$activity['duration']." - ".strftime(DATE_FORMAT." ".TIME_FORMAT, $activity['date'])."<br>
                        ".($activity['adults']+$activity['children'])." ".$texts['PERSONS']." - 
                        ".$texts['ADULTS'].": ".$activity['adults']." / 
                        ".$texts['CHILDREN'].": ".$activity['children']."<br>
                        ".$texts['PRICE']." : ".formatPrice($activity['amount']*CURRENCY_RATE)." ".$texts['INCL_VAT']."</p>";
                    }
                }
                
                if($row['comments'] != "") echo "<p><b>".$texts['COMMENTS']."</b><br>".nl2br($row['comments'])."</p>";
                
                echo "<p>".$texts['TOTAL']." : <b>".formatPrice($row['total']*CURRENCY_RATE)." ".$texts['INCL_TAX']."</b><br>";
                                
                $result_tax = $db->query("SELECT * FROM pm_booking_tax WHERE id_booking = ".$id_booking);
                if($result_tax !== false && $db->last_row_count() > 0){
                    foreach($result_tax as $tax){
                        echo $tax['name'].": ".formatPrice($tax['amount']*CURRENCY_RATE)."<br>";
                    }
                }
                echo "</p>";
                
                if(ENABLE_DOWN_PAYMENT == 1 && $row['down_payment'] > 0)
                    echo "<p>".$texts['DOWN_PAYMENT']." : <b>".formatPrice($row['down_payment']*CURRENCY_RATE)." ".$texts['INCL_TAX']."</b></p>";
                    
                echo "<p><strong>".$texts['PAYMENT']."</strong><p>";
                
                echo "<p>".$texts['PAYMENT_METHOD']." : ".$row['payment_method']."<br>";
                echo $texts['STATUS'].": ";
                switch($row['status']){
                    case 1: echo $texts['AWAITING']; break;
                    case 2: echo $texts['CANCELLED']; break;
                    case 3: echo $texts['REJECTED_PAYMENT']; break;
                    case 4: echo $texts['PAYED']; break;
                    default: echo $texts['AWAITING']; break;
                }
                echo "<br>";
                if($row['status'] == 4){
                    echo $texts['PAYMENT_DATE']." : ".strftime(DATE_FORMAT." ".TIME_FORMAT, $row['payment_date'])."<br>";
                    if(!empty($row['down_payment'])) echo $texts['DOWN_PAYMENT']." : ".formatPrice($row['down_payment']*CURRENCY_RATE)."<br>";
                    if(!empty($row['trans'])) echo $texts['NUM_TRANSACTION']." : ".$row['trans'];
                }
                echo "</p>";
            } ?>
        </div>
        <?php
    }
} ?>
