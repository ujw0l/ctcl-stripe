<?php
/*
 Plugin Name:CTCL Stripe
 Plugin URIhttps://github.com/ujw0l/ctcl-stripe/blob/main/ctcl-stripe.php
 Description: CT commerce lite stripe payments addon, ecommerce
 Version: 1.0.0
 Author: Ujwol Bastakoti
 Author URI:https://ujw0l.github.io/
 Text Domain:  ctcl-stripe
 License: GPLv2
*/
if(class_exists('ctclBillings')){

    class ctclStripe extends ctclBillings{

    /**
     * payment id
     */
    public $paymentId = 'ctcl_stripe';

    /**
     * payment name
     */
    public $paymentName = 'Stripe';

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
        self::displayOptionsUser();
        self::adminPanelHtml();
        self::registerOptions();
        self::requiredWpAction();
    }

    /**
     * rgister form options
     */
public function registerOptions(){

    register_setting($this->settingFields,'ctcl_activate_stripe');
    register_setting($this->settingFields,'ctcl_stripe_test_mode');
    register_setting($this->settingFields,'ctc_stripe_test_publishable_key');
    register_setting($this->settingFields,'ctc_stripe_test_secret_key');
    register_setting($this->settingFields,'ctc_stripe_live_publishable_key');
    register_setting($this->settingFields,'ctc_stripe_live_secret_key');

}

/**
 * 
 */
public function requiredWpAction(){
    add_action( 'wp_enqueue_scripts', array($this,'enequeFrontendJs' ));
    add_action( 'wp_enqueue_scripts', array($this,'enequeFrontendCss' ));
}
/**
   * eneque frontend JS files
   */

  public function enequeFrontendJs(){
    wp_enqueue_script('ctclStripeJs', $this->stripeFilePath."js/{$this->paymentId}.js");
    wp_localize_script('ctclStripeJs','ctclParams',array());
   }

   /**
   * eneque frontend CSS files
   */

  public function enequeFrontendCss(){
    wp_enqueue_style( 'ctclStripeCss', $this->stripeFilePath."js/{$this->paymentId}.js"); 
}


      /**
     * create admin panel content
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
            $html .=  '<div class="ctcl-business-setting-row"><label for"ctcl-cash-on-deblivery"  class="ctcl-cash-on-delivery-label">'.__('Activate Stripe :','ctcl-stripe').'</label>';
            $html .= "<span><input id='ctcl-activate-stripe' {$activate} type='checkbox' name='ctcl_activate_stripe' value='1'></span></div>";

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
            array_push($val,array('settingFields'=>$this->settingFields,'formHeader'=>__("Stripe Payment",'ctcl-stripe'),'formSetting'=>'ctcl_payment_setting','html'=>$html));
      return $val;
        },30,1);
    }

    }

    
    new ctclStripe();


}else{

    function sample_admin_notice__success() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e( 'Plugin requires CTC Lite plugin to work, please install it.', 'ctcl-stripe' ); ?></p>
        </div>
        <?php
    }
    add_action( 'admin_notices', 'sample_admin_notice__success' );
}
