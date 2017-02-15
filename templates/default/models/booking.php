<?php
$field_notice = array();
$msg_error = "";
$msg_success = "";
$room_stock = 1;
$max_people = 30;

if(isset($_POST['num_adults'])) $num_adults = $_POST['num_adults'];
elseif(isset($_SESSION['book']['adults'])) $num_adults = $_SESSION['book']['adults'];
else $num_adults = 1;

if(isset($_POST['num_children'])) $num_children = $_POST['num_children'];
elseif(isset($_SESSION['book']['children'])) $num_children = $_SESSION['book']['children'];
else $num_children = 0;

if(isset($_SESSION['book']['from_date'])) $from_time = $_SESSION['book']['from_date'];
else $from_time = time();

if(isset($_SESSION['book']['to_date'])) $to_time = $_SESSION['book']['to_date'];
else $to_time = time()+86400;

$from_date = date("d/m/Y", $from_time);
$to_date = date("d/m/Y", $to_time);

if(isset($_POST['from_date'])) $from_date = htmlentities($_POST['from_date'], ENT_QUOTES, "UTF-8");
if(isset($_POST['to_date'])) $to_date = htmlentities($_POST['to_date'], ENT_QUOTES, "UTF-8");

if(isset($_POST['room_id']) && is_numeric($_POST['room_id'])) $room_id = $_POST['room_id'];
else $room_id = 0;

if(isset($_POST['book']) || (ENABLE_BOOKING_REQUESTS == 1 && isset($_POST['request']))){
	
	if(isset($_SESSION['book'])) unset($_SESSION['book']);
    $num_adults = $_POST['adults'];
    $num_children = $_POST['children'];
    $num_nights = $_POST['nights'];
    
    $_SESSION['book']['room'] = $_POST['room'];
    $_SESSION['book']['room_id'] = $_POST['id_room'];
    $_SESSION['book']['from_date'] = $_POST['from_date'];
    $_SESSION['book']['to_date'] = $_POST['to_date'];
    $_SESSION['book']['nights'] = $num_nights;
    $_SESSION['book']['adults'] = $num_adults;
    $_SESSION['book']['children'] = $num_children;
    $_SESSION['book']['extra_services'] = array();
    
    if(isset($_POST['book'])){
        $_SESSION['book']['amount_rooms'] = $_POST['amount'];
        $_SESSION['book']['amount_activities'] = 0;
        $_SESSION['book']['amount_services'] = 0;
        
        $_SESSION['book']['duty_free_rooms'] = $_POST['duty_free'];
        $_SESSION['book']['duty_free_activities'] = 0;
        $_SESSION['book']['duty_free_services'] = 0;
       
        $_SESSION['book']['tax_rooms_amount'] = 0;
        $_SESSION['book']['tax_activities_amount'] = 0;
        $_SESSION['book']['tax_services_amount'] = 0;
        
        $_SESSION['book']['taxes'] = array();
        
        if(isset($_POST['taxes']) && is_array($_POST['taxes'])){
            foreach($_POST['taxes'] as $tax_id => $tax_amount){
                $_SESSION['book']['tax_rooms_amount'] += $tax_amount;
                $_SESSION['book']['taxes'][$tax_id]['rooms'] = $tax_amount;
            }
        }
        
        $tourist_tax = (TOURIST_TAX_TYPE == "fixed") ? $num_adults*$num_nights*TOURIST_TAX : $_SESSION['book']['amount_rooms']*TOURIST_TAX/100;
        
        $_SESSION['book']['tourist_tax'] = (ENABLE_TOURIST_TAX == 1) ? $tourist_tax : 0;
        
        $_SESSION['book']['total'] = $_SESSION['book']['duty_free_rooms']+$_SESSION['book']['tax_rooms_amount']+$_SESSION['book']['tourist_tax'];
        $_SESSION['book']['down_payment'] = (ENABLE_DOWN_PAYMENT == 1 && DOWN_PAYMENT_RATE > 0) ? $_SESSION['book']['total']*DOWN_PAYMENT_RATE/100 : 0;
    }
    
    if(isset($_SESSION['book']['id'])) unset($_SESSION['book']['id']);
    
    $result_activity = $db->query("SELECT * FROM pm_activity WHERE checked = 1 AND lang = ".LANG_ID);
    if(isset($_SESSION['book']['activities'])) unset($_SESSION['book']['activities']);
    
    if($result_activity !== false && $db->last_row_count() > 0){
        $_SESSION['book']['activities'] = array();
        header("Location: ".DOCBASE.$sys_pages['booking-activities']['alias']);
    }else
        header("Location: ".DOCBASE.$sys_pages['details']['alias']);
    
    exit();
}

$num_people = $num_adults+$num_children;

if(!is_numeric($num_adults)) $field_notice['num_adults'] = $texts['REQUIRED_FIELD'];
if(!is_numeric($num_children)) $field_notice['num_children'] = $texts['REQUIRED_FIELD'];

if($from_date == "") $field_notice['from_date'] = $texts['REQUIRED_FIELD'];
else{
    $from_time = explode("/", $from_date);
    $from_time = gmmktime(0, 0, 0, $from_time[1], $from_time[0], $from_time[2]);
    if(!is_numeric($from_time)) $field_notice['from_date'] = $texts['REQUIRED_FIELD'];
}
if($to_date == "") $field_notice['to_date'] = $texts['REQUIRED_FIELD'];
else{
    $to_time = explode("/", $to_date);
    $to_time = gmmktime(0, 0, 0, $to_time[1], $to_time[0], $to_time[2]);
    if(!is_numeric($to_time)) $field_notice['to_date'] = $texts['REQUIRED_FIELD'];
}

$period = $to_time-$from_time;
$num_nights = ceil($period/86400);

if(count($field_notice) == 0){

    if($num_nights <= 0) $msg_error .= $texts['NO_AVAILABILITY'];
    else{
        $days = array();
        $booked = array();

        $query_book = "
            SELECT stock, id_room, from_date, to_date
            FROM pm_booking as b, pm_room as r
            WHERE
                lang = ".DEFAULT_LANG."
                AND id_room = r.id
                AND status = 4
                AND r.checked = 1
                AND from_date < ".$to_time."
                AND to_date > ".$from_time."
            GROUP BY b.id";
        $result_book = $db->query($query_book);
        if($result_book !== false){
            foreach($result_book as $i => $row){
                $start_date = $row['from_date'];
                $end_date = $row['to_date'];
                $id_room = $row['id_room'];
                $room_stock = $row['stock'];

                $d = 0;
                $start = ($start_date < $from_time) ? $from_time : $start_date;
                $end = ($end_date > $to_time) ? $to_time : $end_date;
                $dst = date("I", $start);
                
                for($date = $start; $date <= $end; $date += 86400){

                    $cur_dst = date("I", $date);
                    if($dst != $cur_dst){
                        if($cur_dst == 0) $date += 3600;
                        else $date -= 3600;
                        $dst = $cur_dst;
                    }
                    $days[$id_room][$date] = isset($days[$id_room][$date]) ? $days[$id_room][$date]+1 : 1;
                    
                    if($days[$id_room][$date]+1 > $room_stock && !in_array($date, $booked)) $booked[$id_room][] = $date;
                }
            }
        }
        $amount = 0;
        $total_nights = 0;
        $res_room = array();
        
        $tax_id = 0;
        $result_incl_tax = $db->prepare("SELECT * FROM pm_tax WHERE id = :tax_id AND checked = 1 AND value > 0 GROUP BY id ORDER BY rank LIMIT 1");
        $result_incl_tax->bindParam(":tax_id", $tax_id);
        
        $taxes_id = "";
        $result_tax = $db->prepare("SELECT * FROM pm_tax WHERE (FIND_IN_SET(id, :taxes_id) OR id = :tax_id) AND checked = 1 AND value > 0 GROUP BY id ORDER BY rank");
        $result_tax->bindParam(":taxes_id", $taxes_id);
        $result_tax->bindParam(":tax_id", $tax_id);
        
        $query_rate = "
            SELECT DISTINCT name, min_nights, max_nights, days, max_adults, max_children, min_people, max_people, id_room, start_date, end_date, ra.price, child_price, discount, discount_type, people, price_sup, fixed_sup, day_start, day_end, id_tax, taxes
            FROM pm_rate as ra, pm_room as ro, pm_package as p, pm_lang as l
            WHERE
                ro.lang = l.id
                AND l.checked = 1
                AND id_package = p.id
                AND id_room = ro.id
                AND ro.checked = 1
                AND (end_lock IS NULL OR end_lock < ".$from_time." OR
                    start_lock IS NULL OR start_lock > ".$to_time.")
                AND start_date <= ".$to_time."
                AND end_date >= ".$from_time;
        if(!empty($booked)) $query_rate .= " AND id_room NOT IN(".implode(",", array_keys($booked)).")";
        $query_rate .= "
            ORDER BY min_nights DESC";

        $result_rate = $db->query($query_rate);
        if($result_rate !== false){
            foreach($result_rate as $i => $row){

                $id_room = $row['id_room'];
                $start_date = $row['start_date'];
                $end_date = $row['end_date'];
                $price = $row['price'];
                $child_price = $row['child_price'];
                $discount = $row['discount'];
                $discount_type = $row['discount_type'];
                $people = $row['people'];
                $price_sup = $row['price_sup'];
                $fixed_sup = $row['fixed_sup'];
                $day_start = $row['day_start'];
                $day_end = $row['day_end'];
                $days = explode(",", $row['days']);
                $tax_id = $row['id_tax'];
                $taxes_id = $row['taxes'];
                $min_stay = $row['min_nights'];
                $max_stay = $row['max_nights'];
                $min_people = $row['min_people'];
                $max_people = $row['max_people'];
                $max_adults = $row['max_adults'];
                $max_children = $row['max_children'];
                
                if(!isset($res_room[$id_room]['days'])) $res_room[$id_room]['days'] = array();
                
                $from_n = date("N", $from_time);
                $to_n = date("N", $to_time);
                
                $error = false;
                if($num_nights < $min_stay){
                    if(!isset($res_room[$id_room]['min_stay'])) $res_room[$id_room]['min_stay'] = $min_stay;
                    $error = true;
                    $res_room[$id_room]['notice'] = $texts['MIN_NIGHTS']." : ".$min_stay;
                }
                if($num_adults+$num_children > $max_people){
                    $error = true;
                    $res_room[$id_room]['notice'] = $texts['MAX_PEOPLE']." : ".$max_people;
                }
                if($num_adults+$num_children < $min_people){
                    $error = true;
                    $res_room[$id_room]['notice'] = $texts['MIN_PEOPLE']." : ".$min_people;
                }
                if($num_adults > $max_adults){
                    $error = true;
                    $res_room[$id_room]['notice'] = $texts['MAX_ADULTS']." : ".$max_adults;
                }
                if($num_children > $max_children){
                    $error = true;
                    $res_room[$id_room]['notice'] = $texts['MAX_CHILDREN']." : ".$max_children;
                }
                
                if($error === false){
					
                    /// package with default conditions
                    if(($num_nights >= $min_stay || empty($min_stay))
                    && ($num_nights <= $max_stay || empty($max_stay))
                    && ($from_n == $day_start || empty($day_start))
                    && ($to_n == $day_end || empty($day_end))){
                        
						// get common period between current rate and selected period
						$start = ($start_date < $from_time) ? $from_time : $start_date;
						$end = ($end_date > $to_time) ? $to_time : $end_date;
						if($start_date > $from_time) $start-= 86400;
						
						$start = gmmktime(0, 0, 0, date("n", $start), date("j", $start), date("Y", $start));
						$end = gmmktime(0, 0, 0, date("n", $end), date("j", $end), date("Y", $end));
						if(date("I", $start)) $start -= 3600;
						if(date("I", $end)) $end -= 3600;
						
						// number of nights
						$nnights = 0;
						$dst = date("I", $start);
						for($date = $start; $date < $end; $date += 86400){
							$cur_dst = date("I", $date);
							if($dst != $cur_dst){
								if($cur_dst == 1) $date -= 3600;
								else $date += 3600;
								$dst = $cur_dst;
							}
							$d = date("N", $date);
							
							if(!in_array($date, $res_room[$id_room]['days']) && in_array($d, $days)){
								$res_room[$id_room]['days'][] = $date;
								$nnights++;
							}
						}

						if($num_people > $people && $people > 0){
							
							$extra_adults = ($num_adults > $people) ? $num_adults-$people : 0;
							$extra_children = ($num_children > 0) ? $num_people-$people-$extra_adults : 0;
							
							if($child_price == 0 && $price_sup > 0) $child_price = $price_sup;
							if($extra_children > 0) $price += $child_price*$extra_children;
							if($price_sup > 0) $price += $price_sup*$extra_adults;
						}
						
						$price = $nnights*$price;
						$full_price = $price;
						if($discount > 0){
							if($discount_type == "fixed") $price = $price-($nnights*$discount);
							elseif($discount_type == "rate") $price = $price-($price*$discount/100);
						}

						if(!isset($res_room[$id_room]['total_nights']) || $res_room[$id_room]['total_nights']+$nnights <= $num_nights){
							
							if(!isset($res_room[$id_room]['amount'])) $res_room[$id_room]['amount'] = 0;
							$res_room[$id_room]['amount'] += $price;
							
							if(!isset($res_room[$id_room]['full_price'])) $res_room[$id_room]['full_price'] = 0;
							$res_room[$id_room]['full_price'] += $full_price;
							
							if(!isset($res_room[$id_room]['total_nights'])) $res_room[$id_room]['total_nights'] = 0;
							$res_room[$id_room]['total_nights'] += $nnights;
							
							if(!isset($res_room[$id_room]['fixed_sup'])) $res_room[$id_room]['fixed_sup'] = 0;
							if($fixed_sup > $res_room[$id_room]['fixed_sup'])
								$res_room[$id_room]['fixed_sup'] = $fixed_sup;
							
							$duty_free = $price;
							$duty_free_sup = $fixed_sup;
							if($result_incl_tax->execute() !== false && $db->last_row_count() > 0){
								$incl_tax = $result_incl_tax->fetch();
								$duty_free = $price/($incl_tax['value']/100+1);
								$duty_free_sup = $res_room[$id_room]['fixed_sup']/($incl_tax['value']/100+1);
							}
							
							if(!isset($res_room[$id_room]['duty_free'])) $res_room[$id_room]['duty_free'] = 0;
							$res_room[$id_room]['duty_free'] += $duty_free;
							$res_room[$id_room]['duty_free_sup'] = $duty_free_sup;
							
							if($result_tax->execute() !== false){
								foreach($result_tax as $tax){
									if(!isset($res_room[$id_room]['taxes'][$tax['id']]['amount'])) $res_room[$id_room]['taxes'][$tax['id']]['amount'] = 0;
									$res_room[$id_room]['taxes'][$tax['id']]['amount'] += $duty_free*($tax['value']/100);
									$res_room[$id_room]['taxes'][$tax['id']]['fixed_sup'] = $duty_free_sup*($tax['value']/100);
								}
							}
							
							$res_room[$id_room]['min_stay'] = ((isset($res_room[$id_room]['min_stay']) && $min_stay > $res_room[$id_room]['min_stay']) || !isset($res_room[$id_room]['min_stay'])) ? $min_stay : 0;
							if($num_nights < $res_room[$id_room]['min_stay']){
								$res_room[$id_room]['error'] = true;
								$res_room[$id_room]['notice'] = $texts['MIN_NIGHTS']." : ".$res_room[$id_room]['min_stay'];
							}
						}
                    }
                }else
                    $res_room[$id_room]['error'] = true;
            }
            
            foreach($res_room as $id_room => $result){
                if(!isset($result['amount']) || $result['amount'] == 0 || $result['total_nights'] != $num_nights) $res_room[$id_room]['error'] = true;
                elseif(isset($res_room[$id_room]['error'])) unset($res_room[$id_room]['error']);
            }

            if(empty($res_room)) $msg_error .= $texts['NO_AVAILABILITY'];
        }
    }
}

if(isset($_GET['action'])){
    if($_GET['action'] == "confirm")
        $msg_success .= "<p class=\"text-center lead\">".$texts['PAYMENT_SUCCESS_NOTICE']."</p>";
    elseif($_GET['action'] == "cancel")
        $msg_error .= "<p class=\"text-center lead\">".$texts['PAYMENT_CANCEL_NOTICE']."</p>";
}

/* ==============================================
 * CSS AND JAVASCRIPT USED IN THIS MODEL
 * ==============================================
 */
$javascripts[] = DOCBASE."js/plugins/jquery.event.calendar/js/jquery.event.calendar.js";

if(is_file(SYSBASE."js/plugins/jquery.event.calendar/js/languages/jquery.event.calendar.".LANG_TAG.".js"))
    $javascripts[] = DOCBASE."js/plugins/jquery.event.calendar/js/languages/jquery.event.calendar.".LANG_TAG.".js";
else
    $javascripts[] = DOCBASE."js/plugins/jquery.event.calendar/js/languages/jquery.event.calendar.en.js";
    
$stylesheets[] = array("file" => DOCBASE."js/plugins/jquery.event.calendar/css/jquery.event.calendar.css", "media" => "all");

require(getFromTemplate("common/header.php", false)); ?>

<section id="page">
    
    <?php include(getFromTemplate("common/page_header.php", false)); ?>
    
    <div id="content" class="pt30 pb30">
        
        <?php
        if($page['text'] != ""){ ?>
            <div class="container mb20"><?php echo $page['text']; ?></div>
            <?php
        } ?>
        
        <div class="container boxed mb20">
            <legend><?php echo $texts['AVAILABILITIES']; ?></legend>
            <div class="alert alert-success" style="display:none;"></div>
            <div class="alert alert-danger" style="display:none;"></div>
            <?php include(getFromTemplate("common/search.php", false)); ?>
        </div>
        <div class="container boxed">
            <div class="mb20">
                <p>
                    <?php echo $texts['CHECK_IN']." <b>".$from_date."</b> ".$texts['CHECK_OUT']." <b>".$to_date."</b><br>";
                    if(isset($num_nights) && $num_nights > 0) echo "<b>".$num_nights."</b> ".$texts['NIGHTS']." - ";
                    echo "<b>".($num_adults+$num_children)."</b> ".$texts['PERSONS']; ?>
                </p>
            </div>
            <?php
            $id_facility = 0;
            $result_facility_file = $db->prepare("SELECT * FROM pm_facility_file WHERE id_item = :id_facility AND checked = 1 AND lang = ".DEFAULT_LANG." AND type = 'image' AND file != '' ORDER BY rank LIMIT 1");
            $result_facility_file->bindParam(":id_facility", $id_facility);

            $room_facilities = "0";
            $result_facility = $db->prepare("SELECT * FROM pm_facility WHERE lang = ".LANG_ID." AND FIND_IN_SET(id, :room_facilities) ORDER BY rank LIMIT 8");
            $result_facility->bindParam(":room_facilities", $room_facilities);

            $id_room = 0;
            $result_rate = $db->prepare("
                SELECT DISTINCT(price), type
                FROM pm_rate
                WHERE
                    id_room = :id_room
                    AND price IN(SELECT MIN(price) FROM pm_rate WHERE id_room = :id_room)
                ORDER BY price, CASE type
                    WHEN 'week' THEN 1
                    WHEN 'mid-week' THEN 2
                    WHEN 'week-end' THEN 3
                    WHEN '2-nights' THEN 4
                    WHEN 'night' THEN 5
                    ELSE 6 END
                LIMIT 1");
            $result_rate->bindParam(":id_room", $id_room);

            $result_room_file = $db->prepare("SELECT * FROM pm_room_file WHERE id_item = :id_room AND checked = 1 AND lang = ".LANG_ID." AND type = 'image' AND file != '' ORDER BY rank LIMIT 1");
            $result_room_file->bindParam(":id_room", $id_room, PDO::PARAM_STR);

            $query_room = "SELECT * FROM pm_room WHERE checked = 1 AND lang = ".LANG_ID." ORDER BY";
            if($room_id != 0) $query_room .= " CASE WHEN id = ".$room_id." THEN 1 ELSE 4 END,";
            if(!empty($res_room)) $query_room .= " CASE WHEN id IN(".implode(",", array_keys($res_room)).") THEN 3 ELSE 4 END,";
            $query_room .= " rank";
            $result_room = $db->query($query_room);
            if($result_room !== false){
                foreach($result_room as $row){
                    $id_room = $row['id'];
                    $room_title = $row['title'];
                    $room_alias = $row['alias'];
                    $room_subtitle = $row['subtitle'];
                    $room_descr = $row['descr'];
                    $room_price = $row['price'];
                    $room_stock = $row['stock'];
                    $max_adults = $row['max_adults'];
                    $max_children = $row['max_children'];
                    $max_people = $row['max_people'];
                    $min_people = $row['min_people'];
                    $room_facilities = $row['facilities'];
                    
                    $min_price = $room_price;
                    $result_rate->execute();
                    if($result_rate !== false && $db->last_row_count() > 0){
                        $row = $result_rate->fetch();
                        $price = $row['price'];
                        if($price > 0) $min_price = $price;
                    }
                    $type = $texts['NIGHT'];
                    if(!isset($res_room[$id_room])
                    || isset($res_room[$id_room]['error'])
                    || ($num_adults+$num_children > $max_people)
                    || ($num_adults+$num_children < $min_people)
                    || ($num_adults > $max_adults)
                    || ($num_children > $max_children)){
                        $amount = $min_price;
                        $full_price = 0;
                    }else{
                        $amount = $res_room[$id_room]['amount']+$res_room[$id_room]['fixed_sup'];
                        $full_price = $res_room[$id_room]['full_price']+$res_room[$id_room]['fixed_sup'];
                        $type = $num_nights." ".$texts['NIGHTS'];
                    } ?>

                    <form action="<?php echo DOCBASE.$sys_pages['booking']['alias']; ?>" method="post">
                        <input type="hidden" name="room" value="<?php echo $room_title; ?>">
                        <input type="hidden" name="id_room" value="<?php echo $id_room; ?>">
                        <input type="hidden" name="from_date" value="<?php echo $from_time; ?>">
                        <input type="hidden" name="to_date" value="<?php echo $to_time; ?>">
                        <input type="hidden" name="nights" value="<?php echo $num_nights; ?>">
                        <input type="hidden" name="adults" value="<?php echo $num_adults; ?>">
                        <input type="hidden" name="children" value="<?php echo $num_children; ?>">
                        <?php
                        if(isset($res_room[$id_room]) && !isset($res_room[$id_room]['error'])){ ?>
                            <input type="hidden" name="amount" value="<?php echo number_format($amount, 10, ".", ""); ?>">
                            <input type="hidden" name="duty_free" value="<?php echo number_format($res_room[$id_room]['duty_free']+$res_room[$id_room]['duty_free_sup'], 10, ".", ""); ?>">
                            
                            <?php
                            if(isset($res_room[$id_room]['taxes'])){
                                foreach($res_room[$id_room]['taxes'] as $tax_id => $tax){
                                    $tax_amount = $tax['amount'];
                                    if(isset($tax['fixed_sup'])) $tax_amount += $tax['fixed_sup']; ?>
                                    <input type="hidden" name="taxes[<?php echo $tax_id; ?>]" value="<?php echo number_format($tax_amount, 10, ".", ""); ?>">
                                    <?php
                                }
                            }
                        } ?>
                        <div class="row booking-result">
                            <div class="col-md-3">
                                <?php
                                $result_room_file->execute();
                                if($result_room_file !== false && $db->last_row_count() > 0){
                                    $row = $result_room_file->fetch(PDO::FETCH_ASSOC);

                                    $file_id = $row['id'];
                                    $filename = $row['file'];
                                    $label = $row['label'];

                                    $realpath = SYSBASE."medias/room/medium/".$file_id."/".$filename;
                                    $thumbpath = DOCBASE."medias/room/medium/".$file_id."/".$filename;
                                    $zoompath = DOCBASE."medias/room/big/".$file_id."/".$filename;

                                    if(is_file($realpath)){ ?>
                                        <div class="img-container md">
                                            <img alt="<?php echo $label; ?>" src="<?php echo $thumbpath; ?>" itemprop="photo">
                                        </div>
                                        <?php
                                    }
                                } ?>
                            </div>
                            <div class="col-lg-4 col-md-3 col-sm-4">
                                <h3><?php echo $room_title; ?></h3>
                                <h4><?php echo $room_subtitle; ?></h4>
                                <?php echo strtrunc(strip_tags($room_descr), 120); ?>
                                <div class="clearfix mt10">
                                    <?php
                                    $result_facility->execute();
                                    if($result_facility !== false && $db->last_row_count() > 0){
                                        foreach($result_facility as $row){
                                            $id_facility = $row['id'];
                                            $facility_name = $row['name'];
                                            
                                            $result_facility_file->execute();
                                            if($result_facility_file !== false && $db->last_row_count() > 0){
                                                $row = $result_facility_file->fetch();
                                                
                                                $file_id = $row['id'];
                                                $filename = $row['file'];
                                                $label = $row['label'];
                                                
                                                $realpath = SYSBASE."medias/facility/big/".$file_id."/".$filename;
                                                $thumbpath = DOCBASE."medias/facility/big/".$file_id."/".$filename;
                                                    
                                                if(is_file($realpath)){ ?>
                                                    <span class="facility-icon">
                                                        <img alt="<?php echo $facility_name; ?>" title="<?php echo $facility_name; ?>" src="<?php echo $thumbpath; ?>" class="tips">
                                                    </span>
                                                    <?php
                                                }
                                            }
                                        } ?>
                                        <span class="facility-icon">
                                            <a href="<?php echo DOCBASE.$pages[9]['alias']."/".text_format($room_alias); ?>" title="<?php echo $texts['READMORE']; ?>" class="tips">...</a>
                                        </span>
                                        <?php
                                    } ?>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-3 text-center sep">
                                <div class="price">
                                    <span itemprop="priceRange"><?php echo formatPrice($amount*CURRENCY_RATE); ?></span>
                                    <?php
                                    if($full_price > 0 && $full_price > $amount){ ?>
                                        <br><s class="text-warning"><?php echo formatPrice($full_price*CURRENCY_RATE); ?></s>
                                        <?php
                                    } ?>
                                </div>
                                <div class="mb10 text-muted"><?php echo $texts['PRICE']; ?> / <?php echo $type; ?></div>
                                <?php echo $texts['CAPACITY']; ?> : <i class="fa fa-male"></i>x<?php echo $max_people; ?>
                                <p class="lead pt10">
                                    <?php
                                    if(!isset($res_room[$id_room]) || (isset($res_room[$id_room]) && isset($res_room[$id_room]['error']) && !isset($res_room[$id_room]['notice']))){ ?>
                                        <button class="btn btn-danger btn-block" disabled="disabled"><i class="fa fa-warning"></i> <?php echo $texts['NO_AVAILABILITY']; ?></small></button>
                                        <?php
                                        if(ENABLE_BOOKING_REQUESTS == 1){ ?>
                                            <button name="request" class="btn btn-default btn-block"><i class="fa fa-comment"></i> <?php echo $texts['MAKE_A_REQUEST']; ?></small></button>
                                            <?php
                                        }
                                    }elseif(isset($res_room[$id_room]) && isset($res_room[$id_room]['error']) && isset($res_room[$id_room]['notice'])){ ?>
                                        <button class="btn btn-danger btn-block" disabled="disabled"><i class="fa fa-warning"></i> <small><?php echo $res_room[$id_room]['notice']; ?></small></button>
                                        <?php
                                    }elseif(isset($res_room[$id_room]) && !isset($res_room[$id_room]['error'])){ ?>
                                        <button name="book" class="btn btn-success btn-lg btn-block"><i class="fa fa-hand-o-right"></i> <?php echo $texts['BOOK'] ?></button>
                                        <?php
                                    } ?>
                                    <span class="clearfix"></span>
                                    <a class="btn btn-primary mt10 btn-block" href="<?php echo DOCBASE.$pages[9]['alias']."/".text_format($room_alias); ?>">
                                        <i class="fa fa-plus-circle"></i>
                                        <?php echo $texts['READMORE']; ?>
                                    </a>
                                </p>
                            </div>
                            <div class="col-lg-3 col-md-4 col-sm-5 sep">
                                <div class="hb-calendar" data-cur_month="<?php echo date("n", $from_time); ?>" data-cur_year="<?php echo date("Y", $from_time); ?>" data-custom_var="room=<?php echo $id_room; ?>" data-day_loader="<?php echo getFromTemplate("common/get_days.php"); ?>"></div>
                            </div>
                        </div>
                        <hr>
                    </form>
                    <?php
                }
            } ?>
        </div>
    </div>
</section>
