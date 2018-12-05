<?php

ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);

    error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );
    include 'dbconnect.php';

    $Q = "SELECT * FROM oc_t_user ORDER BY pk_i_id LIMIT 100";
    $result = mysqli_query($ownai_db, $Q);
    $new_user_id = 0; $new_user_id = 0; $current_user_id = 0;

    if ($result){
        $count = mysqli_num_rows($result);
        if ($count > 0){
            while ($resRow = mysqli_fetch_assoc($result)){
                //current user Id
                $current_user_id = $resRow['pk_i_id'];
                $phone =  str_replace("+","",$resRow['s_phone_mobile']);
                if (getUserIdFromNewDb($phone, $tengai_db) > 0){
                    $new_user_id = getUserIdFromNewDb($phone, $tengai_db);
    		logEvent('user', 'created new user - old_id:'.$current_user_id.', new_id:'.$new_user_id);    
                }else{
                    $new_user_id = createNewUserOnNewDb($resRow, $tengai_db);
    		logEvent('user', 'created new user - old_id:'.$current_user_id.', new_id:'.$new_user_id);    
                }

                //fetch all posts fromt this user
                $PQ = "SELECT * FROM oc_t_item AS a 
    					  LEFT JOIN oc_t_item_description AS b ON a.pk_i_id = b.fk_i_item_id 
    					  LEFT JOIN oc_t_item_location AS c ON a.pk_i_id = c.fk_i_item_id 
    					  LEFT JOIN oc_t_item_resource AS d ON a.pk_i_id = d.fk_i_item_id 
    					  WHERE a.fk_i_user_id = '".$current_user_id."' ORDER BY a.pk_i_id;";
                $p_result = mysqli_query($ownai_db, $PQ);
                $p_count = mysqli_num_rows($p_result);
                if ($p_count > 0){
                    while ($postRow = mysqli_fetch_assoc($p_result)){

                        $slug = str_replace(' ','-',trim($postRow['s_title'])).$postRow['pk_i_id'];
                        $cat_array = getCategoryId($postRow['fk_i_category_id']);
                        $parent_cat_id = $cat_array['parent_id'];
                        $child_cat_id = $cat_array['child_id'];

                        //generate SQL statement to inject post data
                        echo $new_post_Q = "
    						INSERT INTO posts (title, description, category_id, user_id, country_id, city_id, suburb_id, post_address, 
						    price, status, whatsupp_status, 
						    created_at, post_type, is_dod,
						    sf_entity_id, in_stock, store_customer_id, alternative_contacts, subcategory_id, slug, bumped_up_at) 
    						VALUES ('".$postRow['s_title']."','".$postRow['s_description']."','".$parent_cat_id."', '$new_user_id', 56,'".getCityId($postRow['s_region'])."','".getSuburbId($postRow['s_city'])."', '".$postRow['s_address']."', '".getSuburbId($postRow['i_price'])."', '".$postRow['fk_moderator_action']."', 0, '".$postRow['dt_creat_date']."', 'classified', '0', '1', 1, NULL,NULL,'".$child_cat_id."','".$slug."','".$postRow['dt_creat_date']."')";

                        $host = "cassava-stage-db-001.cfamtribt3cd.ap-south-1.rds.amazonaws.com"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
                        $tengai_db = @mysqli_connect($host, $user, $password, 'tengai_qa_migration') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());

                        if ($tengai_db->query($new_post_Q) === TRUE) {
                            $new_post_id = $tengai_db->insert_id;
    			logEvent('post', 'created new post - for user :'.$new_user_id.', new_post_id:'.$new_post_id);    
        

                            // insert image, THIS ASSUMES ORIGINAL POST HAD 1 IMAGE
                            if (!is_null($postRow['s_name'])){
                                //this can only get Id for a post after creation of the post
                                echo $new_post_image_Q = "INSERT INTO picture_post (picture_id, post_id, featured, image_path) VALUES (NULL, $new_post_id, '1', '".$postRow['s_path'].$postRow['s_name'].'.'.$postRow['s_extension']."');";

                                if ($tengai_db->query($new_post_image_Q) === TRUE) {
                                    echo "Success: Image imported". "<br>";
    				logEvent('image', 'imported image(s) for post: '.$new_post_id);    
                                } else {
                                    echo "Error: " . $new_post_image_Q . "<br>" . $tengai_db->error. "<br>";
    				logEvent('post', 'error setting package for post :'.$new_post_id.', mysql error: '.$new_post_Q.' : '.$tengai_db->error);
                                }
                            }

                            // add post to free package
                            $new_post_package_Q = "INSERT INTO package_post (payment_status, packagetype_id, package_id, post_id, state, pack_amount) VALUES ('success', '5', '9', '$new_post_id','active', NULL);";

                                if ($tengai_db->query($new_post_package_Q) === TRUE) {
                                    echo "Success: Package set". "<br>";
                    logEvent('post', 'set package (Free) for post: '.$new_post_id);    
                                } else {
                                    echo "Error: " . $new_post_package_Q . "<br>" . $tengai_db->error. "<br>";
                    logEvent('post', 'error setting package for post :'.$new_post_id.', mysql error: '.$new_post_package_Q.' : '.$tengai_db->error);    
                                }


                        } else {
                            echo "Error: " . $new_post_Q . "<br>" . $tengai_db->error;
    			logEvent('post', 'error creating post - for user :'.$new_user_id.', old_post_id:'.$new_post_id);    
                        }
                    }
                }else{
                    //no posts under this user
    		logEvent('post', 'no posts - for user :'.$new_user_id);    
                }
            }

        }else{
            echo 'no_users';
    	logEvent('user', 'no users found');    
        }
    }else{
        echo 'system_error';
        logEvent('user', 'system/database error could not fetch users');    
    }


    function getCategoryId($category_id){

        $host = "cassava-stage-db-001.cfamtribt3cd.ap-south-1.rds.amazonaws.com"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
        $ownai_db = @mysqli_connect($host, $user, $password, 'ownai_db') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());
        $Q = "SELECT s_name FROM oc_t_category_description WHERE fk_i_category_id = $category_id";
        $result = mysqli_query($ownai_db, $Q);
        if (mysqli_num_rows($result) > 0){
            $resRow = mysqli_fetch_assoc($result);
            //search for a matching category with this or similar name in the new database
            return getMatchingCategoryId($resRow['s_name']);
        }else{
            return 19;
        }
    }

    function getMatchingCategoryId($category_name){

        $host = "cassava-stage-db-001.cfamtribt3cd.ap-south-1.rds.amazonaws.com"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
        $tengai_db = @mysqli_connect($host, $user, $password, 'tengai_qa_migration') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());

        //$_category_name = mysql_real_escape_string($category_name);

        $_category_name = mysqli_real_escape_string($tengai_db, $category_name);
        $_category_name = str_replace('&amp;', '&', $_category_name);
        $_category_name = strlen($_category_name) > 10 ? mb_substr($_category_name, 0, -4) : $_category_name;

        $Q = "SELECT id FROM categories WHERE name LIKE '%$_category_name%'";
        $result = mysqli_query($tengai_db, $Q);
        if (mysqli_num_rows($result) > 0){
            $resRow = mysqli_fetch_assoc($result);
            return $resRow['id'];
        }
        return 19;

    }

    function getUserIdFromNewDb($mobile_number){

        $host = "cassava-stage-db-001.cfamtribt3cd.ap-south-1.rds.amazonaws.com"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
        $tengai_db = @mysqli_connect($host, $user, $password, 'tengai_qa_migration') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());

        $Q = "SELECT id FROM users WHERE phone = '$mobile_number'";
        $result = mysqli_query($tengai_db, $Q);
        if (mysqli_num_rows($result) > 0){
            $resRow = mysqli_fetch_assoc($result);
            return $resRow['id'];
        }
        return 0;

    }

    function createNewUserOnNewDb($userRow, $tengai_db){

        $phone =  str_replace("+","",$userRow['s_phone_mobile']);
        $user_name = isset($userRow['s_name']) ? $userRow['s_name'] : '-';
        $first_name = isset($userRow['s_name']) ? $userRow['s_name'] : '';
        $last_name = ' - ';
        $email = isset($userRow['s_email_address']) && strlen($userRow['s_email_address']) > 10 ? $userRow['s_email_address'] : $phone.'@tengai.zw';
        $country_code = 263;
        $country_id = 56;
        $city_id = 11;
        $status = 1;
        $is_verified = 1;
        $address = NULL;
        $whatsapp = 0;
        $email_token = NULL;
        $api_token = NULL;
        $original_country = json_encode(['code' => 'zw','name'=>'Zimbabwe','extension' => '263']);
        $sso_auth_token = NULL;
        $password = '$2y$10$N0kIdkPOSIqXJqK5KRkEE.NOiKo4XyctUHMe5NE6MnoHNqwjsCIaq';
        $org_password =$password;
        $created_source =0;
        $deleted_at =NULL;
        $created_at =$userRow['dt_reg_date'];
        $updated_at =$userRow['dt_mod_date'];

        $new_user_Q = "INSERT INTO users 
                (username,first_name,last_name,email,country_code,country_id,city_id,phone,address,password,status,is_verified,whatsapp,email_token,api_token,remember_token,deleted_at,created_at,updated_at,avatar,created_source,saved, org_password, original_country, created_source) 
                VALUES ('".$user_name."','".$first_name."','".$last_name."','".$email."','".$country_code."','".$country_id."','".$city_id."', '".$phone."', '".$address."', '".$password."','".$status."', '".$is_verified."','".$whatsapp."','".$email_token."', '".$api_token."', NULL,NULL,'".$created_at."', '".$updated_at."',NULL,'".$created_source."','','".$org_password."','".$original_country."')";

        if($tengai_db->query($new_user_Q) === TRUE) {
            $new_post_id = $tengai_db->insert_id;

            return $new_post_id;
        } else {
            echo "Error: " . $new_user_Q . "<br>" . $tengai_db->error. "<br>";
        }
        return 0;

    }

    function getCityId($city_name){

        $host = "cassava-stage-db-001.cfamtribt3cd.ap-south-1.rds.amazonaws.com"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
        $tengai_db = @mysqli_connect($host, $user, $password, 'tengai_qa_migration') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());

        $Q = "SELECT id FROM cities WHERE name = '$city_name'";
        $result = mysqli_query($tengai_db, $Q);
        if (mysqli_num_rows($result) > 0){
            $resRow = mysqli_fetch_assoc($result);
            return $resRow['id'];
        }
        return 0;

    }

    function getSuburbId($suburb_name){

        $host = "localhost"; $user = "rajesh_stage"; $password = "bscwzi3K2jNeLWOA";
        $tengai_db = @mysqli_connect($host, $user, $password, 'tengai_qa_migration') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());

        $Q = "SELECT id FROM suburbs WHERE name = '$suburb_name'";
        $result = mysqli_query($tengai_db, $Q);
        if (mysqli_num_rows($result) > 0){
            $resRow = mysqli_fetch_assoc($result);
            return $resRow['id'];
        }
        return 0;

    }

    function logEvent($component, $message){
        $fp = fopen($component.'.txt', 'a');
        fwrite($fp, date("Y-m-d H:i:s").' '.$message."\n"); 
        fclose($fp);
    }


?>
