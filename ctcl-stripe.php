<?php
/*
 Plugin Name:CTCL Stripe
 Plugin URI : https://github.com/ujw0l/ctcl-stripe/blob/main/ctcl-stripe.php
 Description: CT commerce lite stripe payments addon, ecommerce
 Version: 1.2.1
 Author: Ujwol Bastakoti
 Author URI:https://ujw0l.github.io/
 Text Domain:  ctcl-stripe
 License: GPLv2
*/
if(class_exists('ctclBillings')){

    require_once('stripe-php/init.php');
    
    class ctclStripe extends ctclBillings{

    /**
     * Payment id
     */
    public $paymentId = 'ctcl_stripe';

    /**
     * Payment name
     */
    public $paymentName;

    /**
     * Setting Fields
     */
    public $settingFields = 'ctcl_stripe_setting';

    /**
     * Stripe file path
     */
    public $stripeFilePath;

    public function __construct(){
        $this->stripeFilePath = plugin_dir_url(__FILE__);
        $this->paymentName = !empty(get_option('ctcl_stripe_display_label'))?get_option('ctcl_stripe_display_label'):'Stripe';

      
        self::displayOptionsUser();
        self::adminPanelHtml();
        self::registerOptions();
        self::requiredWpAction();
        add_filter('ctcl_process_payment_'.$this->paymentId ,array($this,'processPayment'));
        register_deactivation_hook(__FILE__,  array($this,'stripeDeactivate'));
        
    }



/**
 * Run on deactivation 
 */
public function stripeDeactivate(){

   delete_option('ctcl_activate_stripe');
   delete_option('ctcl_stripe_test_mode');
   delete_option('ctc_stripe_test_publishable_key');
   delete_option('ctc_stripe_test_secret_key');
   delete_option('ctc_stripe_live_publishable_key');
   delete_option('ctc_stripe_live_secret_key');
   delete_option('ctcl_stripe_display_label');
}


    /**
     * Register form options
     */
public function registerOptions(){

    register_setting($this->settingFields,'ctcl_activate_stripe');
    register_setting($this->settingFields,'ctcl_stripe_test_mode');
    register_setting($this->settingFields,'ctc_stripe_test_publishable_key');
    register_setting($this->settingFields,'ctc_stripe_test_secret_key');
    register_setting($this->settingFields,'ctc_stripe_live_publishable_key');
    register_setting($this->settingFields,'ctc_stripe_live_secret_key');
    register_setting($this->settingFields,'ctcl_stripe_display_label');

}
/**
 * 
 */
public function displayOptionsUser(){

    if('1'== get_option('ctcl_activate_stripe')):
        add_filter('ctcl_payment_options',function($val){
            array_push($val,array(
                                    'id'=>$this->paymentId,
                                    'name'=>$this->paymentName,
                                    'html'=>$this->frontendHtml()
            ));
            return $val; 
        },10,1);
    endif;

}

/**
 * Required wp actions
 */
public function requiredWpAction(){
    add_action( 'wp_enqueue_scripts', array($this,'enequeFrontendJs' ));
    add_action( 'wp_enqueue_scripts', array($this,'enequeFrontendCss' ));
}
/**
   * Eneque frontend JS files
   */

  public function enequeFrontendJs(){
    if('1'== get_option('ctcl_activate_stripe')):
        wp_enqueue_script('ctclStripe','https://js.stripe.com/v3/');
         wp_enqueue_script('ctclStripeJs', "{$this->stripeFilePath}js/{$this->paymentId}.js",array('ctclStripe'));
         wp_localize_script('ctclStripeJs','ctclStripeParams',array('stripePubKey'=>'1'== get_option('ctcl_stripe_test_mode')?get_option('ctc_stripe_test_publishable_key'):get_option('ctc_stripe_live_publishable_key')));
    endif;    
}

   /**
   * Eneque frontend CSS files
   */

  public function enequeFrontendCss(){
    wp_enqueue_style( 'ctclStripeCss', "{$this->stripeFilePath}css/{$this->paymentId}.css"); 
}


      /**
     * Create admin panel content
     */
    public function adminPanelHtml(){

        add_filter('ctcl_admin_billings_html',function($val){
            $activate =  '1'=== get_option('ctcl_activate_stripe')? 'checked':'';
            $testMode =  '1'=== get_option('ctcl_stripe_test_mode')? 'checked':'';
            $testPubKey = !empty(get_option('ctc_stripe_test_publishable_key'))? get_option('ctc_stripe_test_publishable_key'):'';
            $testSecKey = !empty(get_option('ctc_stripe_test_secret_key'))? get_option('ctc_stripe_test_secret_key'):'';
            $livePubKey = !empty(get_option('ctc_stripe_live_publishable_key'))? get_option('ctc_stripe_live_publishable_key'):'';
            $liveSecKey = !empty(get_option('ctc_stripe_live_secret_key'))? get_option('ctc_stripe_live_secret_key'):'';

            $html = '<div class="ctcl-content-display ctcl-stripe-settings">';
            $html .= '<div class="ctcl-business-setting-row" ><label>'.__('Stripe Credentials : ','ctcl-stripe').'</label><span> <a href="https://dashboard.stripe.com/developers" target="_blank">'.__('Get it here','ctcl-stripe').'</a></span></div>';
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctcl-activate-stripe"  class="ctcl-activate-stripe-label">'.__('Activate Stripe :','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctcl-activate-stripe' {$activate} type='checkbox' name='ctcl_activate_stripe' value='1'></span></div>";

            $html .=  '<div class="ctcl-business-setting-row"><label for"ctcl-stripe-display-label"  class="ctcl-stripe-display-label-label">'.__('Frontend Option Label :','ctcl-stripe').' </label>';
            $html .= "<span><input id='ctcl-stripe-display-label' type='text' name='ctcl_stripe_display_label' value='".get_option('ctcl_stripe_display_label')."'></span></div><br>";

            $html .=  '<div class="ctcl-business-setting-row"><label for"ctc-stripe-test-publishable-key"  class="ctc-stripe-test-publishable-key-label">'.__('Test Publishable Key :','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctc-stripe-test-publishable-key' type='text' name='ctc_stripe_test_publishable_key' value='{$testPubKey}'></span></div>";
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctc-stripe-test-secret-key"  class="ctc-stripe-test-secret-key-label">'.__('Test Secret Key : ','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctc-stripe-test-secret-key' type='text' name='ctc_stripe_test_secret_key' value='{$testSecKey}'></span></div>";
            
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctcl-stripe-test-mode"  class="ctcl-stripe-test-mode-label">'.__('Test Mode :','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctcl-stripe-test-mode' {$testMode} type='checkbox' name='ctcl_stripe_test_mode' value='1'></span></div>";
            
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctc-stripe-live-publishable-key"  class="ctc-stripe-live-publishable-key-label">'.__('Live Publishable Key :','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctc-stripe-live-publishable-key' type='text' name='ctc_stripe_live_publishable_key' value='{$livePubKey}'></span></div>";
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctc-stripe-live-secret-key"  class="ctc-stripe-live-secret-key-label">'.__('Live Secret Key : ','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctc-stripe-test-secret-key' type='text' name='ctc_stripe_live_secret_key' value='{$liveSecKey}'></span></div>";
            

            $html .= '</div>';
            array_push($val,array(
                                    'settingFields'=>$this->settingFields,
                                    'formHeader'=>__("Stripe Payment",'ctcl-stripe'),
                                    'formSetting'=>'ctcl_payment_setting',
                                    'html'=>$html
                                 )
                                );
      return $val;
        },30,1);
    }

    /**
     * Process payment 
     * 
     * @param $val value from filter to be modified
     */

     public function processPayment($val){
      
        $stripeSecKey = '1'== get_option('ctcl_stripe_test_mode')?get_option('ctc_stripe_test_secret_key'):get_option('ctc_stripe_live_secret_key'); 
        \Stripe\Stripe::setApiKey( $stripeSecKey);
        

        $stripeAmount = round($val['sub-total']*100);
       
        $charge = \Stripe\Charge::create([
            'amount' => $stripeAmount ,
            'currency' => get_option('ctcl_currency'),
            'source' => $val['stripe_token'],
            'receipt_email' =>$val['checkout-email-address']
            
    ]);
        $val['charge_result']= !empty($charge->id) ? TRUE :FALSE;
        $val['failure_message'] = empty($charge->id) ? __("Failed to charge your card.",'ctcl-stripe'):'';
        return $val;
     }

    /**
      * html for frontend
      */
      public function frontendHtml(){
      return '<div id="ctcl-stripe-card-el">
      </div>
      <i id="card-errors"></i>
      ';
      }

    }

    
    new ctclStripe();


}else{
add_thickbox();
   /**
    * If main plugin CTC lite is not installed
    */
    add_action( 'admin_notices', function(){
        echo '<div class="notice notice-error is-dismissible"><p>';
         _e( 'CTCL Stripe Plugin requires CTC Lite plugin installed and activated to work, please do so first.', 'ctcl-stripe' );
         echo '<a href="'.admin_url('plugin-install.php').'?tab=plugin-information&plugin=ctc-lite&TB_iframe=true&width=640&height=500" class="thickbox">'.__('Click Here to install it','ctcl-phone-pay').' </a>'; 
        echo '</p></div>';
    } );
}
