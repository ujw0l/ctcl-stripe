<?php
if (!defined('ABSPATH')) { exit; }
class ctclStripe extends ctclBillings {
    public $paymentId = 'ctcl_stripe';
    public $paymentName;
    public $settingFields = 'ctcl_stripe_setting';
    public $stripeFilePath;
    public function __construct() {
        $this->stripeFilePath = plugin_dir_url(dirname(__DIR__) . '/ctcl-stripe.php');
        $label = sanitize_text_field((string) get_option('ctcl_stripe_display_label', ''));
        // The base plugin interpolates the label directly into HTML attributes.
        $this->paymentName = esc_attr($label ?: __('Pay with Stripe', 'ctcl-stripe'));
        add_action('admin_init', array($this, 'registerOptions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueFrontend'));
        add_filter('ctcl_admin_billings_html', array($this, 'adminPanelHtml'), 30);
        add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/ctcl-stripe.php'), array($this, 'settingsLink'));
        if (CTCL_Stripe_Checkout::available()) {
            add_filter('ctcl_payment_options', function ($options) {
                $options[] = array('id' => $this->paymentId, 'name' => $this->paymentName, 'html' => $this->frontendHtml());
                return $options;
            });
        }
        // Old posted tokens must never reach CTCL's cash-on-delivery processor.
        add_filter('ctcl_process_payment_' . $this->paymentId, function ($data) {
            $data['charge_result'] = false;
            $data['failure_message'] = __('Complete your payment using the secure Stripe form.', 'ctcl-stripe');
            return $data;
        });
    }
    private function assetVersion($file) { return CTCL_STRIPE_VERSION . '.' . filemtime(dirname(__DIR__) . '/' . $file); }
    public static function toggle($value) { return is_scalar($value) && (string) $value === '1' ? '1' : '0'; }
    public static function text($value) { return is_string($value) ? sanitize_text_field(trim($value)) : ''; }
    public function registerOptions() {
        foreach (array('ctcl_activate_stripe','ctcl_stripe_test_mode') as $name) { register_setting($this->settingFields, $name, array('sanitize_callback' => array(__CLASS__, 'toggle'))); }
        foreach (array('ctc_stripe_test_publishable_key','ctc_stripe_test_secret_key','ctc_stripe_live_publishable_key','ctc_stripe_live_secret_key','ctcl_stripe_display_label','ctcl_stripe_test_webhook_secret','ctcl_stripe_live_webhook_secret') as $name) { register_setting($this->settingFields, $name, array('sanitize_callback' => array(__CLASS__, 'text'))); }
    }
    public function settingsLink($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=ctclAdminPanel&tab=billing#ctcl-stripe-settings')) . '">' . esc_html__('Settings', 'ctcl-stripe') . '</a>');
        return $links;
    }
    public function enqueueAdminAssets() {
        if (($_GET['page'] ?? '') !== 'ctclAdminPanel' || ($_GET['tab'] ?? '') !== 'billing') { return; }
        wp_enqueue_style('ctcl-stripe-admin', $this->stripeFilePath . 'css/admin.css', array(), $this->assetVersion('css/admin.css'));
        wp_enqueue_script('ctcl-stripe-admin', $this->stripeFilePath . 'js/admin.js', array(), $this->assetVersion('js/admin.js'), true);
    }
    public function enqueueFrontend() {
        if (!CTCL_Stripe_Checkout::available()) { return; }
        wp_enqueue_style('ctclStripeCss', $this->stripeFilePath . 'css/ctcl_stripe.css', array(), $this->assetVersion('css/ctcl_stripe.css'));
        wp_enqueue_script('ctclStripe', 'https://js.stripe.com/endive/stripe.js', array(), null, true);
        // Independent local controller can show a useful error if Stripe.js is blocked.
        wp_enqueue_script('ctclStripeJs', $this->stripeFilePath . 'js/ctcl_stripe.js', array(), $this->assetVersion('js/ctcl_stripe.js'), true);
        wp_localize_script('ctclStripeJs', 'ctclStripeParams', array(
            'stripePubKey' => CTCL_Stripe_Checkout::key('publishable'), 'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('ctcl_stripe_checkout'),
            'loading' => __('Preparing secure payment…', 'ctcl-stripe'), 'ready' => __('Choose a payment method, then place your order.', 'ctcl-stripe'),
            'paying' => __('Confirming your payment…', 'ctcl-stripe'), 'loadError' => __('Stripe could not load. Refresh the page or choose another payment method.', 'ctcl-stripe'),
            'paymentError' => __('Payment could not be completed. Please try again.', 'ctcl-stripe'),
            'changed' => __('Your checkout details changed. Continue again to refresh your payment form.', 'ctcl-stripe'),
            'continue' => __('Continue to secure payment', 'ctcl-stripe'), 'confirmed' => __('Payment confirmed. Placing your order…', 'ctcl-stripe'),
        ));
    }
    private function keyField($id, $name, $label, $value, $placeholder) {
        ?>
        <div class="ctcl-st-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="ctcl-st-key-control">
                <input type="password" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="off" spellcheck="false">
                <button type="button" class="ctcl-st-reveal" aria-controls="<?php echo esc_attr($id); ?>" aria-pressed="false" data-show="<?php esc_attr_e('Show', 'ctcl-stripe'); ?>" data-hide="<?php esc_attr_e('Hide', 'ctcl-stripe'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Show %s', 'ctcl-stripe'), $label)); ?>"><?php esc_html_e('Show', 'ctcl-stripe'); ?></button>
            </div>
        </div>
        <?php
    }
    public function adminPanelHtml($options) {
        $test = CTCL_Stripe_Checkout::testMode();
        $active = '1' === (string) get_option('ctcl_activate_stripe');
        ob_start();
        ?>
        <div id="ctcl-stripe-settings" class="ctcl-stripe-settings" data-save-label="<?php esc_attr_e('Save Stripe settings', 'ctcl-stripe'); ?>">
            <header class="ctcl-st-hero">
                <div class="ctcl-st-brand" aria-label="Stripe">stripe<span aria-hidden="true">↗</span></div>
                <div class="ctcl-st-hero-copy"><p class="ctcl-st-eyebrow"><?php esc_html_e('PAYMENTS, SIMPLIFIED', 'ctcl-stripe'); ?></p><h2><?php esc_html_e('A smooth way to pay.', 'ctcl-stripe'); ?></h2><p><?php esc_html_e('A considered checkout. Built for your store.', 'ctcl-stripe'); ?></p></div>
                <span class="ctcl-st-status <?php echo $active ? 'is-enabled' : ''; ?>"><?php echo esc_html($active ? __('Enabled', 'ctcl-stripe') : __('Disabled', 'ctcl-stripe')); ?></span>
            </header>
            <div class="ctcl-st-layout">
                <div class="ctcl-st-fields">
                    <section class="ctcl-st-section">
                        <div class="ctcl-st-section-heading"><span class="ctcl-st-step">01</span><div><h3><?php esc_html_e('Your checkout', 'ctcl-stripe'); ?></h3><p><?php esc_html_e('Make Stripe feel at home in your store.', 'ctcl-stripe'); ?></p></div></div>
                        <label class="ctcl-st-switch-row"><span><strong><?php esc_html_e('Enable Stripe', 'ctcl-stripe'); ?></strong><small><?php esc_html_e('Offer Stripe at checkout.', 'ctcl-stripe'); ?></small></span><input id="ctcl-activate-stripe" type="checkbox" name="ctcl_activate_stripe" value="1" <?php checked($active); ?>><span class="ctcl-st-switch" aria-hidden="true"></span></label>
                        <div class="ctcl-st-field"><label for="ctcl-stripe-display-label"><?php esc_html_e('Payment method label', 'ctcl-stripe'); ?></label><input id="ctcl-stripe-display-label" name="ctcl_stripe_display_label" type="text" value="<?php echo esc_attr(get_option('ctcl_stripe_display_label', '')); ?>" placeholder="<?php esc_attr_e('Pay with Stripe', 'ctcl-stripe'); ?>" aria-describedby="ctcl-st-label-help"><p id="ctcl-st-label-help" class="ctcl-st-help"><?php esc_html_e('The name customers see at checkout.', 'ctcl-stripe'); ?></p></div>
                    </section>
                    <section class="ctcl-st-section">
                        <div class="ctcl-st-section-heading"><span class="ctcl-st-step">02</span><div><h3><?php esc_html_e('Connect Stripe', 'ctcl-stripe'); ?></h3><p><?php esc_html_e('Keep your test and live environments separate.', 'ctcl-stripe'); ?></p></div></div>
                        <label class="ctcl-st-switch-row ctcl-st-mode-row"><span><strong><?php esc_html_e('Test mode', 'ctcl-stripe'); ?></strong><small><?php esc_html_e('Use test keys. No real payments.', 'ctcl-stripe'); ?></small></span><input id="ctcl-stripe-test-mode" type="checkbox" name="ctcl_stripe_test_mode" value="1" <?php checked($test); ?>><span class="ctcl-st-switch" aria-hidden="true"></span></label>
                        <div class="ctcl-st-credential-grid">
                        <?php foreach (array('test','live') as $environment) : $isTest = $environment === 'test'; ?>
                        <div class="ctcl-st-credentials" data-environment="<?php echo esc_attr($environment); ?>" data-active="<?php echo $isTest === $test ? 'true' : 'false'; ?>">
                            <div class="ctcl-st-credential-heading"><h4><?php echo esc_html($isTest ? __('Test credentials', 'ctcl-stripe') : __('Live credentials', 'ctcl-stripe')); ?></h4><span class="ctcl-st-active-tag"><?php esc_html_e('IN USE', 'ctcl-stripe'); ?></span></div>
                            <?php $this->keyField('ctc-stripe-' . $environment . '-publishable-key', 'ctc_stripe_' . $environment . '_publishable_key', __('Publishable key', 'ctcl-stripe'), CTCL_Stripe_Checkout::key('publishable', $isTest), 'pk_' . $environment . '_…'); ?>
                            <?php $this->keyField('ctc-stripe-' . $environment . '-secret-key', 'ctc_stripe_' . $environment . '_secret_key', __('Restricted or secret key', 'ctcl-stripe'), CTCL_Stripe_Checkout::key('secret', $isTest), 'rk_' . $environment . '_…'); ?>
                            <?php $this->keyField('ctcl-stripe-' . $environment . '-webhook-secret', 'ctcl_stripe_' . $environment . '_webhook_secret', __('Webhook signing secret', 'ctcl-stripe'), CTCL_Stripe_Checkout::signingSecret($isTest), 'whsec_…'); ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <p class="ctcl-st-help"><?php esc_html_e('Use a restricted API key with Checkout Sessions and PaymentIntents read/write permissions.', 'ctcl-stripe'); ?> <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Find your keys ↗', 'ctcl-stripe'); ?></a></p>
                    </section>
                    <section class="ctcl-st-section ctcl-st-webhook">
                        <div class="ctcl-st-section-heading"><span class="ctcl-st-step">03</span><div><h3><?php esc_html_e('Confirm every order', 'ctcl-stripe'); ?></h3><p><?php esc_html_e('A webhook confirms payment even if the customer closes the page.', 'ctcl-stripe'); ?></p></div></div>
                        <label for="ctcl-stripe-webhook-url"><?php esc_html_e('Webhook endpoint', 'ctcl-stripe'); ?></label><input id="ctcl-stripe-webhook-url" type="url" readonly value="<?php echo esc_url(rest_url('ctcl-stripe/v1/webhook')); ?>">
                        <p class="ctcl-st-help"><?php esc_html_e('Listen for checkout.session.completed, checkout.session.async_payment_succeeded and checkout.session.async_payment_failed. Add the signing secret above. A webhook is required before going live.', 'ctcl-stripe'); ?></p>
                        <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Configure in Stripe ↗', 'ctcl-stripe'); ?></a>
                    </section>
                </div>
                <aside class="ctcl-st-preview" aria-label="<?php esc_attr_e('Checkout appearance preview', 'ctcl-stripe'); ?>">
                    <p class="ctcl-st-eyebrow"><?php esc_html_e('CHECKOUT PREVIEW', 'ctcl-stripe'); ?></p>
                    <div class="ctcl-st-preview-card"><div class="ctcl-st-preview-top"><span class="ctcl-st-card-icon" aria-hidden="true">▤</span><span class="ctcl-st-preview-label"><?php echo esc_html(get_option('ctcl_stripe_display_label') ?: __('Pay with Stripe', 'ctcl-stripe')); ?></span><span class="ctcl-st-check" aria-hidden="true">✓</span></div><p><?php esc_html_e('Choose how you’d like to pay.', 'ctcl-stripe'); ?></p><div class="ctcl-st-preview-tabs"><span><?php esc_html_e('Card', 'ctcl-stripe'); ?></span><span><?php esc_html_e('Other methods', 'ctcl-stripe'); ?></span></div><label><?php esc_html_e('Card details', 'ctcl-stripe'); ?></label><div class="ctcl-st-preview-input">•••• •••• •••• •••• <span>▤</span></div><div class="ctcl-st-preview-inputs"><div>MM / YY</div><div>CVC</div></div><div class="ctcl-st-preview-pay"><?php esc_html_e('Place order', 'ctcl-stripe'); ?><span aria-hidden="true">→</span></div><p class="ctcl-st-powered"><?php esc_html_e('Payment handled by', 'ctcl-stripe'); ?> <strong>stripe</strong></p></div>
                    <p class="ctcl-st-preview-note"><?php esc_html_e('Appearance preview. Stripe shows eligible methods for each customer.', 'ctcl-stripe'); ?></p>
                    <div class="ctcl-st-environment"><span class="ctcl-st-env-label" data-test="<?php esc_attr_e('TEST ENVIRONMENT', 'ctcl-stripe'); ?>" data-live="<?php esc_attr_e('LIVE ENVIRONMENT', 'ctcl-stripe'); ?>"><?php echo esc_html($test ? __('TEST ENVIRONMENT', 'ctcl-stripe') : __('LIVE ENVIRONMENT', 'ctcl-stripe')); ?></span><h4><?php esc_html_e('Ready for a trial run?', 'ctcl-stripe'); ?></h4><p><?php esc_html_e('Test a successful payment, a declined card and authentication before accepting live payments.', 'ctcl-stripe'); ?></p><p class="ctcl-st-currency"><?php echo esc_html(sprintf(__('Store currency: %s', 'ctcl-stripe'), strtoupper(CTCL_Stripe_Checkout::currency()))); ?></p></div>
                </aside>
            </div>
            <div class="ctcl-st-save-status" role="status" data-saved="<?php esc_attr_e('Your saved configuration is shown above.', 'ctcl-stripe'); ?>" data-unsaved="<?php esc_attr_e('You have unsaved changes.', 'ctcl-stripe'); ?>"><?php esc_html_e('Your saved configuration is shown above.', 'ctcl-stripe'); ?></div>
        </div>
        <?php
        $options[] = array('settingFields' => $this->settingFields, 'formHeader' => __('Stripe', 'ctcl-stripe'), 'html' => ob_get_clean());
        return $options;
    }
    public function frontendHtml() {
        ob_start(); ?>
        <div class="ctcl-stripe-checkout">
            <div class="ctcl-stripe-checkout-heading"><span class="ctcl-stripe-card-icon" aria-hidden="true">▤</span><div><h3><?php esc_html_e('Choose how to pay', 'ctcl-stripe'); ?></h3><p><?php esc_html_e('Secure payment, powered by Stripe.', 'ctcl-stripe'); ?></p></div><?php if (CTCL_Stripe_Checkout::testMode()) : ?><span class="ctcl-stripe-test-badge"><?php esc_html_e('Test mode', 'ctcl-stripe'); ?></span><?php endif; ?></div>
            <p class="ctcl-stripe-instructions"><?php esc_html_e('Complete your contact details and shipping choice, then continue to payment.', 'ctcl-stripe'); ?></p>
            <button type="button" class="ctcl-stripe-prepare"><?php esc_html_e('Continue to secure payment', 'ctcl-stripe'); ?><span aria-hidden="true">→</span></button>
            <div id="ctcl-stripe-payment-el"></div>
            <p id="card-errors" role="alert" aria-live="assertive"></p>
            <p class="ctcl-stripe-verified-total" hidden></p>
            <p class="ctcl-stripe-payment-status" role="status" aria-live="polite"></p>
            <div class="ctcl-stripe-checkout-footer"><span aria-hidden="true">♧</span><?php esc_html_e('Your payment details stay with Stripe.', 'ctcl-stripe'); ?><strong>stripe</strong></div>
            <noscript><?php esc_html_e('Enable JavaScript to use Stripe checkout.', 'ctcl-stripe'); ?></noscript>
        </div>
        <?php return ob_get_clean();
    }
}
