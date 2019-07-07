<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'PK_Prosites_Thrivecart_Gateway' ) ) {
    
    class PK_Prosites_Thrivecart_Gateway {
        /**
         * ID of the gateway.
         *
         * @var string
         *
         * @since Unknown
         */
        private static $id = 'thrivecart';
        private static $_instance = null;
        /**
         * separator for thrivecart fields
         * 
         * @var string
         */
        private static $field_sep = '||';
        
        // Some custom parameters specific to gateway
        // These paramaters are for custom webhook
        private static $webhook = 'ps-thrivecart-getway';
        private static $webhook_tag = 'thrive-cart-webhook';
        // This param is for linking to gateway
        private static $gateway_url = '';//your thrivecart url here, e.g http://yout.thrivecart.com
        private static $thrive_account = '';//your thrivecart account username here, e.g yout
        private static $thrive_secret_key = '';//your thrivecart secret key here
        private static $thrivecart_action = 'wpmudev_ps_thrivecart';
        // This parameters are the ones set in settings() method. 
        // We use the same names as we used in the settings fields. No restriction though
        private static $thrivecart_buttontext = null;
        private static $thrivecart_thankyou = null;
        public static function get_instance() {
            if( is_null( self::$_instance ) ){
                self::$_instance = new PK_Prosites_Thrivecart_Gateway();
            }
            return self::$_instance;
            
        }
        private function __construct() {
        	add_action( 'init', array( $this, 'setup' ) );
            add_action( 'parse_request', array( $this, 'parse_request' ) );            
            add_action( self::$thrivecart_action, array( $this, 'webhook_handler' ) );
        }
        public function setup() {
            global $psts;
            self::$thrivecart_buttontext    = $psts->get_setting( 'thrivecart_buttontext' );
            self::$thrivecart_thankyou      = $psts->get_setting( 'thrivecart_thankyou' );
            if ( empty( self::$thrivecart_buttontext ) ) {
                self::$thrivecart_buttontext = 'Checkout';
            }       
            $this->add_rewrite_rules_tags();
            $this->add_rewrite_rules();
            $this->prepare_gateway_setting();
        }
        /**
         * Get gateway's title name. Required in order to show up in active gateways dropdown in admin
         *
         * @since Unknown
         *
         * @return array
         */
        public static function get_name() {
            return array(
                self::$id => __( 'Thrivecart', 'psts' ),
            );
        }
        /**
         * Render the gateway form in front end. Required by Pro Sites in order to display the gateway's checkout form in checkout page
         *
         * @param array  $render_data Data for render.
         * @param array  $args        Arguments for the form.
         * @param int    $blog_id     Blog ID.
         * @param string $domain      Site domain.
         *
         * @since Unknown
         *
         * @return string
         */
        public static function render_gateway( $render_data = array(), $args, $blog_id, $domain ) {
            global $psts, $current_user;
            // Set the default values.
            $activation_key = $user_name = $new_blog = $customer = false;
            $button_product_url = self::$gateway_url.'blog-plan/';
            //annoyingly,thrivecart only allows 4 custom fields, so we have to join some data together
            $url_params = array(
                'blog_id_username'=>'',
                'blog_name_title'=>'',
                'blog_period_level'=>'',
                'blog_activation_key'=>''
            );
            // First we need to clear caches.
            ProSites_Helper_Cache::refresh_cache();
            // Set new/upgrading blog data to render data array.
            foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'activation_key' ) as $key ) {
                $render_data[ $key ] = isset( $render_data[ $key ] ) ? $render_data[ $key ] : ProSites_Helper_Session::session( $key );
            }
            // New blog data.
            $blog_data = empty( $render_data['new_blog_details'] ) ? array() : $render_data['new_blog_details'];
            // Set period and levels.
            $period = empty( $blog_data['period'] ) ? ProSites_Helper_ProSite::default_period() : (int) $blog_data['period'];
            $level  = empty( $blog_data['level'] ) ? 0 : (int) $blog_data['level'];
            $level  = empty( $render_data['upgraded_blog_details']['level'] ) ? $level : (int) $render_data['upgraded_blog_details']['level'];
            // We need to get the email.
            $email = self::get_email( $render_data );
            // Current action.
            $action = self::from_request( 'action', false, 'get' );
            // Set a flag that it is new blog.
            if ( ProSites_Helper_ProSite::allow_new_blog() && ( self::from_request( 'new_blog' ) || 'new_blog' === $action ) ) {
                $new_blog = true;
            }
            // If blog id is found in url.
            $bid = self::from_request( 'bid', $blog_id, 'get' );
            // If blog id is found in url.
            if ( ! empty( $bid ) ) {
                // Blog exists so probably might need to get info from Gateway's API, eg for use info instead of :
                if ( ! empty( $blog_data ) ) {
                    // Get the data.
                    $username  = empty( $blog_data['username'] ) ? '' : $blog_data['username'];
                    $user_email = empty( $blog_data['email'] ) ? '' : $blog_data['email'];
                    $blogname   = empty( $blog_data['blogname'] ) ? '' : $blog_data['blogname'];
                    $blog_title = empty( $blog_data['title'] ) ? '' : $blog_data['title'];
                }
                $url_params['blog_id_username'] = $bid;
            }
            // This is a new blog.
            if ( isset( $render_data['activation_key'] ) ) {
                // Get the activation key.
                $activation_key = $render_data['activation_key'];
                // If new blog details is found.
                if ( ! empty( $blog_data ) ) {
                    // Get the data.
                    $username  = empty( $blog_data['username'] ) ? '' : $blog_data['username'];
                    $user_email = empty( $blog_data['email'] ) ? '' : $blog_data['email'];
                    $blogname   = empty( $blog_data['blogname'] ) ? '' : $blog_data['blogname'];
                    $blog_title = empty( $blog_data['title'] ) ? '' : $blog_data['title'];
                }
                // Since this is a new blog, there is no blog_id as it is still in signups table. We need to use the activation key in button params.
                $url_params['blog_activation_key'] = $activation_key;
            }
            //annoyingly,thrivecart only allows 4 custom fields, so we have to join some data together
            $sep = self::$field_sep;
            $url_params['blog_id_username'] .= $sep.$username;
            $url_params['user_email'] = $user_email;//part of thrivecart required field
            $url_params['blog_name_title'] = $blogname.$sep.$blog_title;
            $main_site = str_replace('https://www.','',get_site_url($bid));
            $main_site = str_replace('https://','',$main_site);
            $main_site = str_replace('http://www.','',$main_site);
            $main_site = str_replace('http://','',$main_site);
            $main_site = ltrim((empty($bid) || ((int)$bid != get_current_blog_id()) ? $blogname.'.' : '').$main_site , '.');
            $url_params['hidden_blog_domain'] = $main_site;
            $url_params['hidden_blog_action'] = 'complete';//do not touch this
            $url_params['hidden_blog_full_url'] = 'http://'.$main_site;//do not touch this
            //$url_params['hidden_blog_token'] = '';//do not touch this - coming soon
            //$url_params['hidden_blog_PayerID'] = '';//do not touch this - coming soon
            $url_params['blog_period_level'] = $period.$sep.$level;
            //do some custom thrivecart url transform.
            //Note: these custom fields should have been created in your thrivecart product
            $thrive_params = self::get_thrive_url_param($url_params,true);
            //$button_product_url .= '?'.str_replace('&amp;','&',urldecode(http_build_query( $thrive_params ) ) );
            ob_start();
            ?>
            <h3><?php esc_html_e( 'Checkout Using Thrivecard Checkout Expirience', 'psts' ); ?></h3>
            <p><?php esc_attr_e( 'The world\'s easiest and most powerful cart platform', 'psts' ); ?></p>
            <form action="<?php echo $button_product_url; ?>" method="get">
                <?php echo $thrive_params; ?>
                <input type="submit" value="<?php echo self::$thrivecart_buttontext; ?>" />
            </form>
            <?php
            return ob_get_clean();
        }

	    /**
	     * Process the checkout form submit.
	     *
	     * When a payment form is submitted handle that here.
	     * You should do all your gateway process here.
	     * Refer Stripe or PayPal gateways for example.
	     * This method is required and should be static.
	     *
	     * @param array  $process_data Processed data.
	     * @param int    $blog_id Site ID.
	     * @param string $domain Site domain.
	    *
	    * @return void
	    */
	    public static function process_checkout_form( $process_data = array(), $blog_id, $domain ) {
	    	// Handle your gateway payement here.
    	}
        
        /**
         * Converts the url params to fit thrivecarts url param
         * 
         * @param array $url_params
         * @param bool $html_input_display(optional) | if true, will return html input types
         * @return mixed
         */
        private static function get_thrive_url_param($url_params,$html_input_display = true){
            $result = array();
            $final_result = '';
            foreach($url_params as $key => $value){
                if(strpos($key,'email') !== false && !empty($value)){
                    $result['passthrough[customer_email]'] = $value;
                    continue;
                }
                $filtered_key = str_replace('_','',$key);
                $filtered_key = str_replace('hiddenblog','',$filtered_key);
                if(strpos($key,'hidden') !== false)
                    $result['passthrough['.$filtered_key.']'] = $value;
                else
                    $result['passthrough[custom_'.$filtered_key.']'] = !empty($value) ? $value : '.';//to prevent empty values
            }
            if($html_input_display){
                foreach($result as $key=>$value){
                    $final_result .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
                }
                //set a jquery for period selector, noticed it doesnt update on its own
                //first get the key value so we can split and join back properly
                $sep = self::$field_sep;
                $period_level_key ='passthrough[custom_blogperiodlevel]';
                $period__level = explode($sep,$result[$period_level_key]);
                $final_result .= '<script type="text/javascript">
                function psTcGatewayVals(){
                var $=jQuery;
                let psTcBlogPL = "";
                let psTcBlogP = "";// period
                let psTcBlogL = "";// level
                if( $("input[name=\''.$period_level_key.'\']").length > 0){
                 psTcBlogPL = $("input[name=\''.$period_level_key.'\']").val().split("'.$sep.'");
                 psTcBlogP = psTcBlogPL[0];// period
                 psTcBlogL = psTcBlogPL[1];// level
                }
                $(".period-selector select.chosen").change(function(){
                    psTcBlogP = $(this).val().split("_")[1];
                    $("input[name=\''.$period_level_key.'\']").val(psTcBlogP+"'.$sep.'"+psTcBlogL);
                });
                $(".choose-plan-button").click(function(){
                    let classDivide = $(this).closest("ul.chosen-plan").attr("class").split(" ")[1].split("-");
                    psTcBlogL = classDivide[2];
                    $("input[name=\''.$period_level_key.'\']").val(psTcBlogP+"'.$sep.'"+psTcBlogL);
                });
                }
                jQuery(document).ready(function(){
                    psTcGatewayVals();
                });
                </script>';
            }
            else{
                $final_result = $result;           
            }
            return $final_result;
        }
        /**
         * Handles the HTTP Request sent from Thrivecart to site's webhook
         *
         * @return bool
         */
        public function webhook_handler() {
            global $psts;
            /*
            * If we are here it means that we have some HTTP Request to the Webhook we have set in this site.
            *
            * Depending on the Request we can retrieve the input with $_POST or file_get_contents
            */
            $input = $_GET;
            if(!$this->is_auth_thrivecart_account($input))
                return false;
            if(!$this->is_good_order($input))//order wasnt successful
                return false;
            //convert the data to array
            $input = $this->thrive_data_json_convert($input);
            // The request from the getway should include information about site/blog, payment and user
            // For site it should include the activation key, especially for first payment.
            // If it is a subscription and this is a recurring payment it can contain the blog_id too
            // For the site it should also contain the level id. From the level id we can get the level name with:
            //$level_name = $psts->get_level_setting( $level, 'name' );
            
            $sep = self::$field_sep;
            $period__level = explode($sep,$this->get_thrivecart_custom_key_val('blog_period_level',$input) );
            $period = $period__level[0];
            $level = $period__level[1];
            $i_t = $this->is_trial($input);
            $letts = $i_t ? '7 days' : $period.' months';//else theres an upsell ish
            $period_timestamp = strtotime('+ '.$letts);
            $exp_timestamp = $period_timestamp;//strtotime('+'.$period.' months');
            $level_name = $psts->get_level_setting( $level, 'name' );
            //default values, for now 
            $on_trial = $i_t ? true : false;
            $recurring = $i_t ? false : true;//if its trial, not reoccuring
            // For payment it should contain :
            // A transaction code. This can be stored in a custom table
            // A transaction/event type. For insance it can be a notification for a subscription creation or update. Or it could be for a successfull or failed payment. Lets not forget about cancellations too.
            // If the transaction type is a successfull payment for a new site ( new site could be when there is no blog_id in HTTP Request. It depends ),
            // we need to activate that signup:
            $blog_id = explode($sep,$this->get_thrivecart_custom_key_val('blog_id_username',$input))[0];
            $is_rec_sub = $this->is_rec_blog_sub($input);
            if($this->is_new_blog_sub($input)){//its a new blog subscription
                $activation_key = $this->get_thrivecart_custom_key_val('blog_activation_key',$input);
                //for the password ish
                $new_blog_details = ProSites_Helper_Session::session( 'new_blog_details' );
                var_dump($new_blog_details);
                $password = $new_blog_details['user_pass'];
                $data = array(
                    'activation_key'=> $activation_key,
                    'new_blog_details'=> array(
                        'user_pass'=> $password
                        )
                    );
                //
                $blog_details = ProSites_Helper_Registration::activate_blog($data,$on_trial,$period,$level,$exp_timestamp,$recurring);
                $blog_id = $blog_details['blog_id'];
                //$result = ProSites_Helper_Registration::activate_blog(
            //    {ACTIVATION KEY FROM HTTP REQUEST},
            //    {CHECK IF IS ON TRIAL},
            //    {PERIOD FROM REQUEST},
            //    {LEVEL FROM REQUEST},
            //    {EXPIRE TIMESTAMP - CALCULATE OR FROM REQUEST},
            //    {IF IT IS RECURRING : true OR false}
            //);//returns array('user_id'=>int,'blog_id'=>int);
            }
            else if($is_rec_sub){
                $url = $this->get_blog_url_from_thrive($input);
                $blog_id = get_blog_id_from_url($url);
            }
            //check if its promo stuff
            $amount_type = $this->is_promo_payment($input) ? 'single' : 'recurring';
            $amount = $this->get_thrive_amount($input,$amount_type,$i_t); //($input['order']['total_str']);
            //if theres no trial, and its not a recurring sub, so first timer :), store the 7 days period, then extend to the normal time frame as instructed frequency
                if(!$i_t && !$is_rec_sub){
                    $trial = true;
                    $letts = $this->get_thrive_time_frame($input,$trial);
                    $period_timestamp = strtotime('+ '.$letts);
                    $exp_timestamp = $period_timestamp;//strtotime('+'.$period.' months');
                    $amount =  $this->get_thrive_amount($input,$amount_type,$trial);
                    $psts->extend($blog_id,$period_timestamp,self::$id,$level,$amount,$exp_timestamp,$recurring,false,'',$trial);
                    //now store again
                    $trial = false;
                    $letts = $this->get_thrive_time_frame($input,$trial);
                    $period_timestamp = strtotime('+ '.$letts);
                    $exp_timestamp = $period_timestamp;//strtotime('+'.$period.' months');
                    $amount =  $this->get_thrive_amount($input,$amount_type,$trial);
                    $psts->extend($blog_id,$period_timestamp,self::$id,$level,$amount,$exp_timestamp,$recurring,false,'',$trial);
                }
                else if($i_t){//its trial
                    $psts->extend($blog_id,$period_timestamp,self::$id,$level,$amount,$exp_timestamp,$recurring,false,'',$on_trial);
                }
                else if($is_rec_sub){//recurring subscription
                    $letts = $this->get_thrive_time_frame($input,$i_t);//$i_t will surely be false here :)
                    $period_timestamp = strtotime('+ '.$letts);
                    $exp_timestamp = $period_timestamp;//strtotime('+'.$period.' months');
                    $psts->extend($blog_id,$period_timestamp,self::$id,$level,$amount,$exp_timestamp,$recurring,false,'',$on_trial);
                }
                // $psts->extend(
                //{BLOG ID},
                //{PERIOD TIMESTAMP},
                //{GATEWAY ID : self::$id},
                //{LEVEL},
                //{AMOUNT PAID},
                //{EXPIRATION TIMESTAMP},
                //{IS RECURRING _ true or false},
                //{SEND A MANUAL NOTIFIACTION - set it to false},
                //{TYPE OF EXTENSION, - manual or trial. You can leave it blank},
                //{ON TRIAL - true or false}
            //);
            // Upon a Cancellation event we will need to cancel site's Pro Site level:
            //$psts->withdraw( {BLOG ID} );
            // You might need some custom option for that blog
            //update_blog_option( {BLOG ID}, 'psts_thrivecart_canceled', 1 );
            return true;
        }
        /**
         * Checks if the order is successful
         * 
         * @param array $input | the $_POST ish
         * @return bool
         */
        private function is_good_order($input){
            $event = $input['event'];
            if($event != 'order.success' && $event != 'order.subscription_payment')//order wasnt successful
                return false;
            return true;
        }
        /**
         * Checks if it's a new blog or a recurring payment
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @return bool
         */
        private function is_new_blog_sub($input){
            $act_key = isset($input['customer']['custom_fields'][$this->filter_thrive_custom_key('blog_activation_key')]) ? trim($input['customer']['custom_fields'][$this->filter_thrive_custom_key('blog_activation_key')]) : ''; 
            $blog_head = isset($input['customer']['custom_fields'][$this->filter_thrive_custom_key('blog_id_username')]) ? explode(self::$field_sep,$input['customer']['custom_fields'][$this->filter_thrive_custom_key('blogidusername')]) : '';
            $id = trim($blog_head[0]);
            if(empty($id) && (!empty($act_key) || $act_key == '.' ) && $input['event'] == 'order.success' )//last condition to make sure its not a renewal ish
                return true;
            return false;
        }
        /**
         * Checks if it's a recurring payment
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @return bool
         */
        private function is_rec_blog_sub($input){
            if( $input['event'] == 'order.subscription_payment' && isset($input['recurring_payment_idx']))//its recurring
                return true;
            return false;
        }
        /**
         * Gets blog domain url
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @return string
         */
        private function get_blog_url_from_thrive($input){
            $blog_head = isset($input['customer']['custom_fields'][$this->filter_thrive_custom_key('blog_name_title')]) ? explode(self::$field_sep,$input['customer']['custom_fields'][$this->filter_thrive_custom_key('blog_name_title')]) : '';
            $main_site = str_replace('https://www.','',network_site_url());
            $main_site = str_replace('https://','',$main_site);
            $main_site = str_replace('http://www.','',$main_site);
            $main_site = str_replace('http://','',$main_site);
            return trim($blog_head[0]).'.'.$main_site;
        }
        /**
         * Checks if it's a trial for 7 days ish
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @return bool
         */
        private function is_trial($input){
            $r = true;
            //$p = '20';
            //$p_s = str_replace('.','',number_format((float)$p,1,'.',''));
            //$_ = '0';
            if(isset($input['order']['charges'])){
                foreach($input['order']['charges'] as $stuff){
                    if($stuff['item_type'] == 'upsell' /*&& $stuff['amount'] == $p_s.$_*/){//there's an upsell, so its not a 7 days trial
                        //its 1 year
                        $r = false;
                        break;
                    }
                }
            }
            else if($this->is_rec_blog_sub($input))// its a recurring sub, cant be trial
                $r = false;
            return $r;
        }
        /**
         * Checks if theres like a promo price, so a single payment
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @return bool
         */
        private function is_promo_payment($input){
            $r = false;
            if(isset($input['order']['charges'])){
                foreach($input['order']['charges'] as $stuff){
                    if($stuff['type'] == 'single'){//theres a promo price
                        $r = true;
                        break;
                    }
                }
            }
            return $r;
        }
        /**
         * Gets the corresponding amount
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @param string $amount_type(optional) | the type, single or recurring
         * @param bool $is_trial(optional) | if it's trial or not
         * @return int
         */
        private function get_thrive_amount($input,$amount_type = 'single',$is_trial = false){
            $price = 0;
            if(isset($input['order']['charges'])){
                $s_type = $amount_type == 'single' ? 'single' : 'recurring';
                $i_type = $is_trial ? 'product' : 'upsell';
                foreach($input['order']['charges'] as $stuff){
                    if($stuff['item_type'] == $i_type && $stuff['type'] == $s_type){//theres a promo price
                        $price = $stuff['amount_str'];
                        break;
                    }
                }
            }
            else if(isset($input['subscription']['amount_str'])){//its a subscription fee
                $price = $input['subscription']['amount_str'];
            }
            return $price;
        }
        /**
         * Gets thrive time frame
         * 
         * This helps us set a time frame for trial period and normal period
         * 
         * @param array $input | the json_decoded post data from thrivecart
         * @param bool $is_trial(optional) | if it's trial or not
         * @return string
         */
        private function get_thrive_time_frame($input,$is_trial = false){
            $str_time = '';
            if(isset($input['order']['charges'])){
                if($is_trial && !$this->is_rec_blog_sub($input))
                    return '7 days';
                $i_type = $is_trial ? 'product' : 'upsell';
                $s_type = 'recurring';//for now we use the recurring version, for trial, its a fixed 7 days
                foreach($input['order']['charges'] as $stuff){
                    if($stuff['item_type'] == $i_type && $stuff['type'] == $s_type){//theres a promo price
                        $str_time = $stuff['quantity'].' '.$stuff['frequency'];
                        break;
                    }
                }
            }
            else{//its a recurring subscription, so lets get the normal time of the user since thrivecart doesnt give us the time frequency
                $sep = self::$field_sep;
                $period__level = explode($sep,$this->get_thrivecart_custom_key_val('blog_period_level',$input) );
                $period = $period__level[0];
                $str_time = $period.' month'.((int)$period == 1 ? '' : 's');
            }
            return $str_time;
        }
        /**
         * Converts the json data to array
         * 
         * @param array $input | the post data from thrivecart
         * @param bool $to_array(optional) | if to convert the json to array or not 
         * @return array
         */
        private function thrive_data_json_convert($input,$to_array = true){
            $result = array();
            foreach($input as $key => $value){
                $val_a = is_string($value) ? json_decode($value, $to_array) : $value;
                $val =  is_array($val_a) && (json_last_error() == JSON_ERROR_NONE) ? $val_a : $value;
                $result[$key] = $val;
            }
            return $result;
        }
        /**
         * Gets value of custom fields from thrivecart
         * 
         * @param string $key | a valid key from the webhook
         * @param array $input | the json_decoded post data from thrivecart
         * @return mixed|bool
         */
        private function get_thrivecart_custom_key_val($key,$input){
            $key = str_replace('_','',$key);
            $val = isset($input['customer']['custom_fields'][$key]) ? $input['customer']['custom_fields'][$key] : false;
            return $val;
        }
        /**
         * Filters for thrive custom key
         * 
         * @param $key
         * @return string
         */
        private function filter_thrive_custom_key($key){
            return str_replace('_','',$key);
        }
        /**
         * Check if its from authentic thrive account
         * 
         * this should be run first before accepting any other data in the webhook
         * 
         * @param array $input | the post data from thrivecart
         * @return bool
         */
        private function is_auth_thrivecart_account($input){
            $account = isset($input['thrivecart_account']) ? $input['thrivecart_account'] : '';
            $secret = isset($input['thrivecart_secret']) ? $input['thrivecart_secret'] : '';
            if(self::$thrive_account == $account && self::$thrive_secret_key == $secret)
                return true;
            return false;
        }
        private function prepare_gateway_setting() {
            //add_action( 'psts_gateway_settings', array( $this, 'settings' ) );
            // For Gateway settings
            // 1. Add gateway admin tab
            add_filter( 'prosites_gateways_tabs', array( $this, 'settings_tab' ) );
            // 2. Load the content for the Gateway Settings
            add_action( 'psts_settings_page', array( $this, 'settings' ) );
            // 2. Add the tab callback function. That callbck is the one that will fetch the settings content
            //add_filter( 'prosites_settings_tabs_render_callback', array( $this, 'settings_tab_callback' ), 20, 2 );
        }
        public function settings_tab( $tabs ) {
            $tabs[ self::$id ] = array(
                'header_save_button'    => true,
                'button_name'           => 'button_name',
                'title'                 => 'Thrivecart',
                'desc'                  => array( 'Use the Thrivecart checkout!' ),
                'url'                   => "admin.php?page=psts-gateways&tab=" . self::$id
            );
            return $tabs;
        }
        public function settings_tab_callback( $render_callback, $active_tab ) {
            if ( $active_tab == self::$id ) {
                $render_callback = array( get_class(), 'settings' );
            }
            return $render_callback;
        }
        public function settings() {
            global $psts;
            ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );
            $class_name = get_class();
            $active_gateways = (array) $psts->get_setting('gateways_enabled');            
            $checked = in_array( $class_name, $active_gateways ) ? 'on' : 'off';
            /**
            * IMPORTANT !!
            * You need at least one form element with name `psts[]`. Else settings won't be saved. Elemnts without `psts[]` name won't be set to PS settings
            */
            ?>

            <div class="inside">
                <p class="description">
                    Learn more about <a href="https://thrivecart.com/" target="_blank"><?php _e( 'Thrivecart here &raquo;', 'psts' ); ?></a>
                </p>

                <p>
                    <?php  
                    printf( 
                        __( 'To use Thrivecart you must enter this webook url <strong>%1$s</strong> in your Thrivecart account under <strong>Settings > API & Webhooks > Webhooks & notifications</strong>.', 'psts' ), 
                        network_site_url( self::$webhook . DIRECTORY_SEPARATOR . self::$webhook_tag ) 
                    );
                    ?>
                </p>
                <p>
                    <?php
                    $d = '<li><strong>['.PK_PS_BLOG_RED_SHORTCODE.']</strong> - Get the url link of the blog</li>
                    ';
                    /*
                    $d ='  
                    <li><strong>['.PK_PS_BLOG_URL_SHORTCODE.']</strong> - Get the url link of the blog</li>
                    <li><strong>['.PK_PS_BLOG_USERNAME_SHORTCODE.']</strong> - Get the username of the user</li>
                    <li><strong>['.PK_PS_BLOG_ADMIN_URL_SHORTCODE.']</strong> - Get the admin url link of the blog</li>
                    <li><strong>['.PK_PS_BLOG_ADMIN_PASSWORD_SHORTCODE.']</strong> - Get the password reset link of the blog</li>
                    ';*/
                    _e( 'You can use the following shortcodes on your custom checkout page to 
                    retrieve the following informations
                    <ul>'.$d.'</ul>', 'psts' ); 
                    ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Enable Gateway', 'psts' ) ?></th>
                        <td>
                            <input type="hidden" name="gateway" value="<?php echo esc_attr( $class_name ); ?>" />
                            <input type="checkbox" name="gateway_active" value="1" <?php checked( $checked, 'on' ); ?> />
                            <input type="hidden" name="submit_gateways" />
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row" class="psts-help-div psts-thrivecart-thankyou"><?php echo esc_html__( 'Thank You Message', 'psts' ) . $psts->help_text( esc_html__( 'Displayed on successful checkout. HTML allowed', 'psts' ) ); ?></th>
                        <td>
                            <textarea name="psts[thrivecart_thankyou]" type="text" rows="4" wrap="soft" id="thrivecart_thankyou" style="width: 100%"><?php echo esc_textarea( stripslashes( $psts->get_setting( 'thrivecart_thankyou' ) ) ); ?></textarea>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row" class="psts-help-div psts-thrivecart-buttontext"><?php echo esc_html__( 'Button text', 'psts' ) . $psts->help_text( esc_html__( 'The text on the buttons that will re-direct to Thrivecart checkout page', 'psts' ) ); ?></th>
                        <td>
                            <input type="text" name="psts[thrivecart_buttontext]" id="thrivecart_buttontext" value="<?php echo esc_textarea( stripslashes( $psts->get_setting( 'thrivecart_thankyou' ) ) ); ?>" />
                        </td>
                    </tr>

                </table>

            
            </div>

            <?php
        }
        public function parse_request( &$wp ) {
            if( array_key_exists( self::$webhook_tag, $wp->query_vars ) ) {                
                do_action( self::$thrivecart_action );
                die(0);
            }
        }
        protected function add_rewrite_rules_tags() {
        	add_rewrite_tag( '%' . self::$webhook_tag . '%', '([^&]+)' );
        }
        protected function add_rewrite_rules() {
            //To use like http://site.com/ps-thrivecart-getway/payment-notification/
            add_rewrite_rule( '^' . self::$webhook . '/([^/]*)/?', 'index.php?' .  self::$webhook_tag . '=$matches[1]', 'top' );
        }
        // Helper functions taken from Stripe Gateway that ws created by Joel James ♥
        /**
         * Get a value from $_POST global.
         *
         * @param string $string  String name.
         * @param mixed  $default Default value.
         * @param string $type    Type of request.
         *
         * @since  Unknown
         *
         * @return mixed
         */
        public static function from_request( $string, $default = false, $type = 'post' ) {
            switch ( $type ) {
                case 'post':
                    // Get data from post.
                    $value = isset( $_POST[ $string ] ) ? $_POST[ $string ] : false; // input var okay.
                    break;
                case 'get':
                    $value = isset( $_GET[ $string ] ) ? $_GET[ $string ] : false; // input var okay.
                    break;
                default:
                    $value = isset( $_REQUEST[ $string ] ) ? $_REQUEST[ $string ] : false; // input var okay.
            }
            // If empty return default value.
            if ( ! empty( $value ) ) {
                return $value;
            }
            return $default;
        }
        /**
         * Get email for the current registration.
         *
         *
         * @param array $process_data Process data.
         *
         * @since Unknown
         *
         * @return string|false
         */
        private static function get_email( $process_data = array() ) {
            global $current_user;
            // First try to get the email.
            $email = empty( $current_user->user_email ) ? false : $current_user->user_email;
            // Email is empty so try to get user email.
            if ( empty( $email ) ) {
                // Let's try to get signup email.
                $email = self::from_request( 'user_email' );
            }
            // Email is empty.
            if ( empty( $email ) ) {
                // Let's try to get signup email.
                $email = self::from_request( 'signup_email' );
            }
            // Again email is empty.
            if ( empty( $email ) ) {
                // Let's try to get from blog email.
                $email = self::from_request( 'blog_email' );
            }
            // In case if email is not set, try to get from process data.
            if ( empty( $email ) && isset( $process_data['new_blog_details']['user_email'] ) ) {
                $email = $process_data['new_blog_details']['user_email'];
            }
            return $email;
        }
    }
    if ( ! function_exists( 'PK_Prosites_Thrivecart_Gateway' ) ) {
    	function pk_prosites_thrivecart_gateway(){    		
    		return PK_Prosites_Thrivecart_Gateway::get_instance();
    	}
    	add_action( 'plugins_loaded', 'pk_prosites_thrivecart_gateway', 10 );
    }
}