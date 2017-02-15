<?php
if(!isset($_SESSION['book']) || count($_SESSION['book']) == 0){
    header("Location: ".DOCBASE.$sys_pages['booking']['alias']);
    exit();
}

$msg_error = "";
$msg_success = "";
$field_notice = array();

$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0;

$result_user = $db->query("SELECT * FROM pm_user WHERE id = ".$db->quote($user_id)." AND checked = 1");
if($result_user !== false && $db->last_row_count() > 0){
    $row = $result_user->fetch();
    
    $firstname = $row['firstname'];
    $lastname = $row['lastname'];
    $login = $row['login'];
    $email = $row['email'];
    $address = $row['address'];
    $postcode = $row['postcode'];
    $city = $row['city'];
    $company = $row['company'];
    $country = $row['country'];
    $mobile = $row['mobile'];
    $phone = $row['phone'];
}else{
    $firstname = "";
    $lastname = "";
    $login = "";
    $email = "";
    $address = "";
    $postcode = "";
    $city = "";
    $company = "";
    $country = "";
    $mobile = "";
    $phone = "";
}

$id = 0;
$comments = "";

if($user_id > 0 && (isset($_POST['book']) || (ENABLE_BOOKING_REQUESTS == 1 && isset($_POST['request'])))){
    
    $firstname = htmlentities($_POST['firstname'], ENT_COMPAT, "UTF-8");
    $lastname = htmlentities($_POST['lastname'], ENT_COMPAT, "UTF-8");
    $address = htmlentities($_POST['address'], ENT_COMPAT, "UTF-8");
    $postcode = htmlentities($_POST['postcode'], ENT_COMPAT, "UTF-8");
    $city = htmlentities($_POST['city'], ENT_COMPAT, "UTF-8");
    $company = htmlentities($_POST['company'], ENT_COMPAT, "UTF-8");
    $country = htmlentities($_POST['country'], ENT_COMPAT, "UTF-8");
    $mobile = htmlentities($_POST['mobile'], ENT_COMPAT, "UTF-8");
    $phone = htmlentities($_POST['phone'], ENT_COMPAT, "UTF-8");
    $comments = htmlentities($_POST['comments'], ENT_COMPAT, "UTF-8");

    if($firstname == "") $field_notice['firstname'] = $texts['REQUIRED_FIELD'];
    if($lastname == "") $field_notice['lastname'] = $texts['REQUIRED_FIELD'];
    if($address == "") $field_notice['address'] = $texts['REQUIRED_FIELD'];
    if($postcode == "") $field_notice['postcode'] = $texts['REQUIRED_FIELD'];
    if($city == "") $field_notice['city'] = $texts['REQUIRED_FIELD'];
    if($country == "" || $country == "0") $field_notice['country'] = $texts['REQUIRED_FIELD'];
    if($phone == "" || preg_match("/([0-9\-\s\+\(\)\.]+)/i", $phone) !== 1) $field_notice['phone'] = $texts['REQUIRED_FIELD'];
    if($email == "" || !preg_match("/^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$/i", $email)) $field_notice['email'] = $texts['INVALID_EMAIL'];

    $result_exists = $db->query("SELECT * FROM pm_user WHERE id != ".$db->quote($user_id)." AND email = ".$db->quote($email));
    if($result_exists !== false && $db->last_row_count() > 0)
        $field_notice['email'] = $texts['ACCOUNT_EXISTS'];
            
    if(count($field_notice) == 0){
    
        $data = array();
        $data['id'] = $user_id;
        $data['firstname'] = $firstname;
        $data['lastname'] = $lastname;
        $data['email'] = $email;
        $data['address'] = $address;
        $data['postcode'] = $postcode;
        $data['city'] = $city;
        $data['company'] = $company;
        $data['country'] = $country;
        $data['mobile'] = $mobile;
        $data['phone'] = $phone;
        $data['edit_date'] = time();

        $result_user = db_prepareUpdate($db, "pm_user", $data);
        if($result_user->execute() !== false){
    
            $_SESSION['book']['id_user'] = $user_id;
            $_SESSION['book']['firstname'] = $firstname;
            $_SESSION['book']['lastname'] = $lastname;
            $_SESSION['book']['email'] = $email;
            $_SESSION['book']['company'] = $company;
            $_SESSION['book']['address'] = $address;
            $_SESSION['book']['postcode'] = $postcode;
            $_SESSION['book']['city'] = $city;
            $_SESSION['book']['phone'] = $phone;
            $_SESSION['book']['mobile'] = $mobile;
            $_SESSION['book']['country'] = $country;
            $_SESSION['book']['comments'] = $comments;
            
            if(isset($_SESSION['book']['id'])) unset($_SESSION['book']['id']);
        
            if(isset($_POST['book'])){
                
                
                
                header("Location: ".DOCBASE.$sys_pages['summary']['alias']);
                exit();
            }elseif(ENABLE_BOOKING_REQUESTS == 1 && isset($_POST['request'])){
                
                $mailContent = "
                <p><strong>".$texts['BILLING_ADDRESS']."</strong><br>
                ".$_SESSION['book']['firstname']." ".$_SESSION['book']['lastname']."<br>";
                if($_SESSION['book']['company'] != "") $mailContent .= $texts['COMPANY']." : ".$_SESSION['book']['company']."<br>";
                $mailContent .= nl2br($_SESSION['book']['address'])."<br>
                ".$_SESSION['book']['postcode']." ".$_SESSION['book']['city']."<br>
                ".$texts['PHONE']." : ".$_SESSION['book']['phone']."<br>";
                if($_SESSION['book']['mobile'] != "") $mailContent .= $texts['MOBILE']." : ".$_SESSION['book']['mobile']."<br>";
                $mailContent .= $texts['EMAIL']." : ".$_SESSION['book']['email']."</p>
                
                <p>".$texts['ROOM']." : <strong>".$_SESSION['book']['room']."</strong><br>
                ".$texts['CHECK_IN']." <strong>".strftime(DATE_FORMAT, $_SESSION['book']['from_date'])."</strong><br>
                ".$texts['CHECK_OUT']." <strong>".strftime(DATE_FORMAT, $_SESSION['book']['to_date'])."</strong><br>
                <strong>".$_SESSION['book']['nights']."</strong> ".$texts['NIGHTS']."<br>
                <strong>".($_SESSION['book']['adults']+$_SESSION['book']['children'])."</strong> ".$texts['PERSONS']." - 
                ".$texts['ADULTS'].": <strong>".$_SESSION['book']['adults']."</strong> / 
                ".$texts['CHILDREN'].": <strong>".$_SESSION['book']['children']."</strong></p>";
                
                if(!empty($_SESSION['book']['extra_services'])){
                    $mailContent .= "<p><strong>".$texts['EXTRA_SERVICES']."</strong><br>";
                    foreach($_SESSION['book']['extra_services'] as $i => $extra){
                        $mailContent .= $extra['title']." x ".$extra['qty']." : ".formatPrice($extra['price']*CURRENCY_RATE)." ".$texts['INCL_VAT']."<br>";
                    }
                    $mailContent .= "</p>";
                }
                
                if($_SESSION['book']['comments'] != "") $mailContent .= "<p><b>".$texts['COMMENTS']."</b><br>".nl2br($_SESSION['book']['comments'])."</p>";
                
                if(sendMail(EMAIL, OWNER, "Booking request", $mailContent, $_SESSION['book']['email'], $_SESSION['book']['firstname']." ".$_SESSION['book']['lastname'])){
                    sendMail($_SESSION['book']['email'], $_SESSION['book']['firstname']." ".$_SESSION['book']['lastname'], "Booking request", $mailContent);
                    $msg_success .= $texts['MAIL_DELIVERY_SUCCESS'];
                    $lastname = "";
                    $firstname = "";
                    $email = "";
                    $address = "";
                    $postcode = "";
                    $city = "";
                    $company = "";
                    $country = "";
                    $mobile = "";
                    $phone = "";
                    $comments = "";
                }else
                    $msg_error .= $texts['MAIL_DELIVERY_FAILURE'];
            }
        }else
            $msg_error .= $texts['ACCOUNT_EDIT_FAILURE'];
    }else
        $msg_error .= $texts['FORM_ERRORS'];
}

require(getFromTemplate("common/header.php", false)); ?>

<section id="page">
    
    <?php include(getFromTemplate("common/page_header.php", false)); ?>
    
    <div id="content" class="pt30 pb30">
        <div class="container">
            <div class="row mb30" id="booking-breadcrumb">
                <div class="col-sm-2 col-sm-offset-<?php echo isset($_SESSION['book']['activities']) ? "1" : "2"; ?>">
                    <a href="<?php echo DOCBASE.$sys_pages['booking']['alias']; ?>">
                        <div class="breadcrumb-item done">
                            <i class="fa fa-calendar"></i>
                            <span><?php echo $sys_pages['booking']['name']; ?></span>
                        </div>
                    </a>
                </div>
                <?php
                if(isset($_SESSION['book']['activities'])){ ?>
                    <div class="col-sm-2">
                        <a href="<?php echo DOCBASE.$sys_pages['booking-activities']['alias']; ?>">
                            <div class="breadcrumb-item done">
                                <i class="fa fa-ticket"></i>
                                <span><?php echo $sys_pages['booking-activities']['name']; ?></span>
                            </div>
                        </a>
                    </div>
                    <?php
                } ?>
                <div class="col-sm-2">
                    <div class="breadcrumb-item active">
                        <i class="fa fa-info-circle"></i>
                        <span><?php echo $sys_pages['details']['name']; ?></span>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="breadcrumb-item">
                        <i class="fa fa-list"></i>
                        <span><?php echo $sys_pages['summary']['name']; ?></span>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="breadcrumb-item">
                        <i class="fa fa-credit-card"></i>
                        <span><?php echo $sys_pages['payment']['name']; ?></span>
                    </div>
                </div>
            </div>
            
            <?php
            if($page['text'] != ""){ ?>
                <div class="clearfix mb20"><?php echo $page['text']; ?></div>
                <?php
            } ?>

            <form method="post" action="<?php echo DOCBASE.$page['alias']; ?>" role="form" class="ajax-form">
                
                <div class="alert alert-success" style="display:none;"></div>
                <div class="alert alert-danger" style="display:none;"></div>
            
                <div class="row">
                    <div class="col-md-6">
                        <?php
                        if($user_id == 0){ ?>
                            <fieldset>
                                <legend><?php echo $texts['ALREADY_HAVE_ACCOUNT']; ?></legend>
                                <div class="row form-group">
                                    <label class="col-lg-3 control-label"><?php echo $texts['USERNAME']; ?></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="user" value="<?php echo $login; ?>"/>
                                        <div class="field-notice" rel="user"></div>
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <label class="col-lg-3 control-label"><?php echo $texts['PASSWORD']; ?></label>
                                    <div class="col-lg-9">
                                        <input type="password" class="form-control" name="pass"/>
                                        <div class="field-notice" rel="pass" value=""></div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-7 col-lg-4 col-lg-offset-3 text-left">
                                        <a class="popup-modal open-pass-form" href="#user-popup"><?php echo $texts['FORGOTTEN_PASSWORD']; ?></a>
                                    </div>
                                    <div class="col-sm-5 text-right">
                                        <a href="#" class="btn btn-primary sendAjaxForm" data-action="<?php echo getFromTemplate("common/register/login.php"); ?>" data-refresh="true"><i class="fa fa-power-off"></i> <?php echo $texts['LOG_IN']; ?></a>
                                    </div>
                                </div>
                            </fieldset>
                            <?php
                        } ?>
                        <fieldset>
                            <legend><?php echo ($user_id == 0) ? $texts['I_SIGN_UP'] : $texts['MY_ACCOUNT']; ?></legend>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['FIRSTNAME']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="firstname" value="<?php echo $firstname; ?>"/>
                                    <div class="field-notice" rel="firstname"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['LASTNAME']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="lastname" value="<?php echo $lastname; ?>"/>
                                    <div class="field-notice" rel="lastname"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['EMAIL']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="email" value="<?php echo $email; ?>"/>
                                    <div class="field-notice" rel="email"></div>
                                </div>
                            </div>
                            <?php
                            if($user_id == 0){ ?>
                                <div class="row form-group">
                                    <label class="col-lg-3 control-label"><?php echo $texts['USERNAME']; ?> *</label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="username" value="<?php echo $login; ?>"/>
                                        <div class="field-notice" rel="username"></div>
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <label class="col-lg-3 control-label"><?php echo ($user_id > 0) ? $texts['NEW_PASSWORD'] : $texts['PASSWORD']; ?></label>
                                    <div class="col-lg-9">
                                        <input type="password" class="form-control" name="password" value=""/>
                                        <div class="field-notice" rel="password"></div>
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <label class="col-lg-3 control-label"><?php echo $texts['PASSWORD_CONFIRM']; ?></label>
                                    <div class="col-lg-9">
                                        <input type="password" class="form-control" name="password_confirm" value=""/>
                                        <div class="field-notice" rel="password_confirm"></div>
                                    </div>
                                </div>
                                <?php
                            } ?>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['ADDRESS']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="address" value="<?php echo $address; ?>"/>
                                    <div class="field-notice" rel="address"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['POSTCODE']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="postcode" value="<?php echo $postcode; ?>"/>
                                    <div class="field-notice" rel="postcode"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['CITY']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="city" value="<?php echo $city; ?>"/>
                                    <div class="field-notice" rel="city"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['COUNTRY']; ?> *</label>
                                <div class="col-lg-9">
                                    <select class="form-control" name="country">
                                        <option value="0">-</option>
                                        <?php
                                        $result_country = $db->query("SELECT * FROM pm_country");
                                        if($result_country !== false){
                                            foreach($result_country as $i => $row){
                                                $id_country = $row['id'];
                                                $country_name = $row['name'];
                                                $selected = ($country == $country_name) ? " selected=\"selected\"" : "";
                                                
                                                echo "<option value=\"".$country_name."\"".$selected.">".$country_name."</option>";
                                            }
                                        } ?>
                                    </select>
                                    <div class="field-notice" rel="country"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['PHONE']; ?> *</label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="phone" value="<?php echo $phone; ?>"/>
                                    <div class="field-notice" rel="phone"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['MOBILE']; ?></label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="mobile" value="<?php echo $mobile; ?>"/>
                                    <div class="field-notice" rel="mobile"></div>
                                </div>
                            </div>
                            <div class="row form-group">
                                <label class="col-lg-3 control-label"><?php echo $texts['COMPANY']; ?></label>
                                <div class="col-lg-9">
                                    <input type="text" class="form-control" name="company" value="<?php echo $company; ?>"/>
                                    <div class="field-notice" rel="company"></div>
                                </div>
                            </div>
                            <?php
                            if($user_id == 0){ ?>
                                <div class="form-group row">
                                    <div class="col-sm-12 text-right">
                                        <i class="text-muted"> * <?php echo $texts['REQUIRED_FIELD']; ?> </i><br>
                                        <a href="#" class="btn btn-primary sendAjaxForm" data-action="<?php echo getFromTemplate("common/register/signup.php"); ?>"><i class="fa fa-power-off"></i> <?php echo $texts['SIGN_UP']; ?></a>
                                    </div>
                                </div>
                                <?php
                            } ?>
                        </fieldset>
                    </div>
                    <div class="col-md-6">
                        <fieldset class="mb20">
                            <legend><?php echo $texts['BOOKING_DETAILS']; ?></legend>
                            <div class="ctaBox">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h3><?php echo $_SESSION['book']['room']; ?></h3>
                                        <p>
                                            <?php
                                            echo $texts['CHECK_IN']." <strong>".strftime(DATE_FORMAT, $_SESSION['book']['from_date'])."</strong><br>
                                            ".$texts['CHECK_OUT']." <strong>".strftime(DATE_FORMAT, $_SESSION['book']['to_date'])."</strong><br>
                                            <strong>".$_SESSION['book']['nights']."</strong> ".$texts['NIGHTS']." -
                                            <strong>".($_SESSION['book']['adults']+$_SESSION['book']['children'])."</strong> ".$texts['PERSONS']; ?>
                                        </p>
                                    </div>
                                    <?php
                                    if(isset($_SESSION['book']['amount_rooms'])){ ?>
                                        <div class="col-md-6">
                                            <span class="pull-right lead text-center">
                                                <?php echo formatPrice($_SESSION['book']['amount_rooms']*CURRENCY_RATE); ?><br/>
                                            </span>
                                        </div>
                                        <?php
                                    } ?>
                                </div>
                                <?php
                                if(ENABLE_TOURIST_TAX == 1 && isset($_SESSION['book']['tourist_tax'])){ ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <p>
                                                <strong><?php echo $texts['TOURIST_TAX']; ?></strong>
                                                <span class="pull-right"><?php echo formatPrice($_SESSION['book']['tourist_tax']*CURRENCY_RATE); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                } ?>
                            </div>
                        </fieldset>
                        <?php
                        if(isset($_SESSION['book']['activities']) && count($_SESSION['book']['activities']) > 0){ ?>
                            <fieldset class="mb20">
                                <legend><?php echo $texts['ACTIVITIES']; ?></legend>
                                <?php
                                foreach($_SESSION['book']['activities'] as $id_activity => $activity){ ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p>
                                                <?php
                                                echo "<strong>".$activity['title']."</strong> - ".$activity['duration']."<br>
                                                <strong>".strftime(DATE_FORMAT." ".TIME_FORMAT, $activity['session_date'])."</strong> -
                                                <strong>".($activity['adults']+$activity['children'])."</strong> ".$texts['PERSONS']; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <span class="pull-right">
                                                <?php echo formatPrice($activity['amount']*CURRENCY_RATE); ?><br/>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                } ?>
                            </fieldset>
                            <?php
                        } ?>
                        <fieldset class="mb20">
                            <legend><?php echo $texts['EXTRA_SERVICES']; ?></legend>
                            <?php
                            $result_service = $db->query("SELECT * FROM pm_service WHERE rooms REGEXP '(^|,)".$_SESSION['book']['room_id']."(,|$)' AND lang = ".LANG_ID." AND checked = 1 ORDER BY rank");
                            if($result_service !== false){
                                foreach($result_service as $i => $row){
                                    $id_service = $row['id'];
                                    $service_title = $row['title'];
                                    $service_descr = $row['descr'];
                                    $service_long_descr = $row['long_descr'];
                                    $service_price = $row['price'];
                                    $service_type = $row['type'];

                                    if($service_type == "person") $service_price *= $_SESSION['book']['adults']+$_SESSION['book']['children'];
                                    if($service_type == "person-night" || $service_type == "qty-person-night") $service_price *= ($_SESSION['book']['adults']+$_SESSION['book']['children'])*$_SESSION['book']['nights'];
                                    if($service_type == "qty-night" || $service_type == "night") $service_price *= $_SESSION['book']['nights'];

                                    $checked = array_key_exists($id_service, $_SESSION['book']['extra_services']) ? " checked=\"checked\"" : ""; ?>

                                    <div class="row form-group">
                                        <label class="col-sm-<?php echo (strpos($service_type, "qty") !== false) ? 7 : 10; ?> col-xs-9">
                                            <input type="checkbox" name="extra_services[]" value="<?php echo $id_service; ?>" class="sendAjaxForm" data-action="<?php echo getFromTemplate("common/update_booking.php"); ?>" data-target="#total_booking"<?php echo $checked;?>>
                                            <?php
                                            echo $service_title;
                                            if($service_descr != ""){ ?>
                                                <br><small><?php echo $service_descr; ?></small>
                                                <?php
                                            }
                                            if($service_long_descr != ""){ ?>
                                                <br><small><a href="#service_<?php echo $id_service; ?>" class="popup-modal"><?php echo $texts['READMORE']; ?></a></small>
                                                <div id="service_<?php echo $id_service; ?>" class="white-popup-block mfp-hide">
                                                    <?php echo $service_long_descr; ?>
                                                </div>
                                                <?php
                                            } ?>
                                        </label>
                                        <?php
                                        if(strpos($service_type, "qty") !== false){
                                            $qty = isset($_SESSION['book']['extra_services'][$id_service]['qty']) ? $_SESSION['book']['extra_services'][$id_service]['qty'] : 1; ?>
                                            <div class="col-sm-3 col-xs-9">
                                                <div class="input-group">
                                                    <span class="input-group-btn">
                                                        <button class="btn btn-default btn-number" data-field="qty_service_<?php echo $id_service; ?>" data-type="minus" disabled="disabled" type="button">
                                                            <i class="fa fa-minus"></i>
                                                        </button>
                                                    </span>
                                                    <input class="form-control input-number sendAjaxForm" type="text" max="20" min="1" value="<?php echo $qty; ?>" name="qty_service_<?php echo $id_service; ?>" data-action="<?php echo getFromTemplate("common/update_booking.php"); ?>" data-target="#total_booking">
                                                    <span class="input-group-btn">
                                                        <button class="btn btn-default btn-number" data-field="qty_service_<?php echo $id_service; ?>" data-type="plus" type="button">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php
                                        } ?>
                                        <div class="col-sm-2 col-xs-3 text-right">
                                            <?php
                                            if(strpos($service_type, "qty") !== false) echo "x ";
                                            echo formatPrice($service_price*CURRENCY_RATE); ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } ?>
                        </fieldset>
                        <?php
                        if(isset($_SESSION['book']['amount_rooms'])){ ?>
                            <hr>
                            <div id="total_booking" class="mb15">
                                <div class="row">
                                    <div class="col-xs-6">
                                        <h3>
                                            <?php
                                            echo $texts['TOTAL']." <small>(".$texts['INCL_TAX'].")</small>"; ?>
                                        </h3>
                                    </div>
                                    <div class="col-xs-6 lead text-right">
                                        <?php echo formatPrice($_SESSION['book']['total']*CURRENCY_RATE); ?>
                                    </div>
                                </div>
                                <?php
                                $tax_id = 0;
                                $result_tax = $db->prepare("SELECT * FROM pm_tax WHERE id = :tax_id AND checked = 1 AND value > 0 AND lang = ".LANG_ID." ORDER BY rank");
                                $result_tax->bindParam(":tax_id", $tax_id);
                                foreach($_SESSION['book']['taxes'] as $tax_id => $taxes){
                                    $tax_amount = 0;
                                    foreach($taxes as $amount) $tax_amount += $amount;
                                    if($tax_amount > 0){
                                        if($result_tax->execute() !== false && $db->last_row_count() > 0){
                                            $row = $result_tax->fetch(); ?>
                                            <div class="row">
                                                <div class="col-xs-6">
                                                    <?php echo $row['name']; ?>
                                                </div>
                                                <div class="col-xs-6 text-right">
                                                    <?php echo formatPrice($tax_amount*CURRENCY_RATE); ?>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    }
                                } ?>
                            </div>
                            <?php
                        } ?>
                        <fieldset>
                            <legend><?php echo $texts['SPECIAL_REQUESTS']; ?></legend>
                            <div class="form-group">
                                <textarea class="form-control" name="comments" rows="5"><?php echo $comments; ?></textarea>
                                <div class="field-notice" rel="comments"></div>
                            </div>
                            <p><?php //echo $texts['BOOKING_TERMS']; ?></p>
                        </fieldset>
                    </div>
                </div>
                
                <a class="btn btn-default btn-lg pull-left" href="<?php echo (isset($_SESSION['book']['activities'])) ? DOCBASE.$sys_pages['booking-activities']['alias'] : DOCBASE.$sys_pages['booking']['alias']; ?>"><i class="fa fa-angle-left"></i> <?php echo $texts['PREVIOUS_STEP']; ?></a>
                <?php
                if($user_id > 0){
                    if(isset($_SESSION['book']['amount_rooms'])){ ?>
                        <button type="submit" class="btn btn-primary btn-lg pull-right" name="book"><?php echo $texts['NEXT_STEP']; ?> <i class="fa fa-angle-right"></i></button>
                        <?php
                    }else{ ?>
                        <button type="submit" class="btn btn-primary btn-lg pull-right" name="request"><i class="fa fa-send"></i> <?php echo $texts['MAKE_A_REQUEST']; ?></button>
                        <?php
                    }
                } ?>
            </form>
        </div>
    </div>
</section>
