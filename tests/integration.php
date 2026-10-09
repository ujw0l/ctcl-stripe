<?php
/** Run with: wp --path=/path/to/wordpress eval-file tests/integration.php */
if (!defined('ABSPATH') || !class_exists('CTCL_Stripe_Checkout')) { throw new RuntimeException('Load the addon in WordPress first.'); }
global $wpdb, $table, $orders, $attempts, $orderIds, $checks;
$processor = new CTCL_Stripe_Checkout();
$table = $wpdb->prefix . 'ctclStripeCheckouts';
$orders = $wpdb->prefix . 'ctclOrders';
$attempts = array();
$orderIds = array();
$checks = 0;
function ctcl_test_check($pass, $message) { global $checks; if (!$pass) { throw new RuntimeException($message); } $checks++; echo "PASS: $message\n"; }
function ctcl_test_throws($call) { try { $call(); return false; } catch (RuntimeException $error) { return true; } }
$source = $wpdb->get_row("SELECT COALESCE(NULLIF(c.details,'{}'),o.orderDetail) AS details FROM $table c LEFT JOIN $orders o ON c.order_id = o.orderId WHERE c.environment = 'test' AND c.session_id IS NOT NULL ORDER BY c.created DESC LIMIT 1", ARRAY_A);
if (!$source) { throw new RuntimeException('Prepare an unpaid test checkout first to provide a real CTCL validation fixture.'); }
$data = json_decode($source['details'], true);
$validate = new ReflectionMethod($processor, 'validatedCheckout');
$validate->setAccessible(true);
// Tests exercise real published CTCL products, actual schema and signing verification; no Stripe charges.
$mailCount = 0;
$orderHooks = 0;
remove_all_actions('ctcl-order-placed');
remove_all_filters('ctcl_data_for_ml');
add_action('ctcl-order-placed', function () use (&$orderHooks) { $orderHooks++; });
add_filter('pre_wp_mail', function () use (&$mailCount) { $mailCount++; return true; });
add_filter('ctcl_custom_email_body', function () { return '<p>Test order.</p>'; });
$secret = 'whsec_local_fixture_only';
add_filter('pre_option_ctcl_stripe_test_webhook_secret', function () use ($secret) { return $secret; });
function ctcl_test_fixture($data) {
    global $wpdb, $table, $attempts, $orderIds;
    $attempt = bin2hex(random_bytes(24));
    $id = 'cs_test_fixture_' . bin2hex(random_bytes(12));
    $orderId = (string) (time() + random_int(500, 10000));
    $fingerprint = hash('sha256', wp_json_encode(array($data, 'usd', true)));
    $wpdb->insert($table, array('attempt'=>$attempt, 'session_id'=>$id, 'order_id'=>$orderId,'fingerprint'=>$fingerprint,'details'=>wp_json_encode($data),'environment'=>'test','state'=>'open','created'=>current_time('mysql',true),'updated'=>current_time('mysql',true)));
    $attempts[] = $attempt; $orderIds[] = $orderId;
    return \Stripe\Checkout\Session::constructFrom(array('id'=>$id,'object'=>'checkout.session','status'=>'complete','payment_status'=>'paid','livemode'=>false,'currency'=>'usd','amount_total'=>CTCL_Stripe_Checkout::minorAmount($data['sub-total'],'usd'),'payment_intent'=>'pi_fixture','metadata'=>array('ctcl_attempt'=>$attempt,'ctcl_fingerprint'=>$fingerprint)));
}
function ctcl_test_webhook($processor, $session, $type, $secret, $signature = true) {
    $payload = wp_json_encode(array('id'=>'evt_fixture_' . bin2hex(random_bytes(6)),'object'=>'event','livemode'=>false,'type'=>$type,'data'=>array('object'=>$session->toArray())));
    $request = new WP_REST_Request('POST','/ctcl-stripe/v1/webhook');
    $request->set_body($payload);
    $now = time();
    $request->set_header('stripe-signature', 't=' . $now . ',v1=' . ($signature ? hash_hmac('sha256',$now . '.' . $payload,$secret) : str_repeat('0',64)));
    return $processor->webhook($request);
}
try {
    $raw = $data;
    if ((float) $raw['total-discount'] === 0.0) { $raw['total-discount'] = '0'; }
    $valid = $validate->invoke($processor, $raw);
    ctcl_test_check(is_array($valid) && $valid['sub-total'] === $data['sub-total'], 'Published CTCL cart validates with pickup, tax and total');
    $tampered = $raw; $tampered['sub-total'] = '1.00';
    ctcl_test_check(ctcl_test_throws(function () use ($validate,$processor,$tampered) { $validate->invoke($processor,$tampered); }), 'Forged cart total is rejected');
    $tampered = $raw; $tampered['shipping_option'] = 'made_up';
    ctcl_test_check(ctcl_test_throws(function () use ($validate,$processor,$tampered) { $validate->invoke($processor,$tampered); }), 'Unavailable shipping option is rejected');
    ctcl_test_check(CTCL_Stripe_Checkout::minorAmount('12.34','usd') === 1234, 'Two-decimal currency uses cents');
    ctcl_test_check(CTCL_Stripe_Checkout::minorAmount('1200','jpy') === 1200, 'Zero-decimal currency uses whole units');
    ctcl_test_check(CTCL_Stripe_Checkout::minorAmount('1200','ugx') === 120000, 'UGX uses Stripe historical two-decimal representation');
    ctcl_test_check(ctcl_test_throws(function () { CTCL_Stripe_Checkout::minorAmount('12.34','jpy'); }), 'Fractional zero-decimal currency is rejected');
    $draftMethod = new ReflectionMethod($processor, 'createDraft');
    $draftMethod->setAccessible(true);
    $draftAttempt = bin2hex(random_bytes(24)); $attempts[] = $draftAttempt;
    $draftFingerprint = hash('sha256', wp_json_encode($data));
    $draft = $draftMethod->invoke($processor, $draftAttempt, $draftFingerprint, $data, true);
    $sameDraft = $draftMethod->invoke($processor, $draftAttempt, $draftFingerprint, $data, true);
    ctcl_test_check(strlen($draft['order_id']) === 10 && abs((int) $draft['order_id'] - time()) < 60 && gmdate('Y-m-d', (int) $draft['order_id']) === gmdate('Y-m-d'), 'Order ID renders the correct date in CTCL');
    ctcl_test_check($sameDraft['order_id'] === $draft['order_id'], 'Repeated checkout preparation retains the same reserved order ID');
    $draftAttempt2 = bin2hex(random_bytes(24)); $attempts[] = $draftAttempt2;
    $draft2 = $draftMethod->invoke($processor, $draftAttempt2, $draftFingerprint, $data, true);
    ctcl_test_check($draft2['order_id'] !== $draft['order_id'], 'Checkouts created in the same second reserve distinct timestamp IDs');
    $session = ctcl_test_fixture($data);
    $session->payment_status = 'unpaid';
    $result = $processor->fulfill($session);
    ctcl_test_check(!$result['paid'] && !$wpdb->get_var($wpdb->prepare("SELECT orderId FROM $orders WHERE orderId = %s",$result['orderId'])), 'Unpaid asynchronous session never creates an order');
    ctcl_test_check(is_wp_error(ctcl_test_webhook($processor,$session,'checkout.session.completed',$secret,false)), 'Forged webhook signature is rejected');
    $validWebhook = ctcl_test_webhook($processor,$session,'checkout.session.completed',$secret);
    ctcl_test_check($validWebhook instanceof WP_REST_Response && $mailCount === 0, 'Signed unpaid completion acknowledges without fulfillment');
    $session->payment_status = 'paid';
    $validWebhook = ctcl_test_webhook($processor,$session,'checkout.session.async_payment_succeeded',$secret);
    ctcl_test_check($validWebhook instanceof WP_REST_Response && $orderHooks === 1 && $mailCount === 1, 'Signed asynchronous success creates CTCL order and invokes notifications once');
    $result = $processor->fulfill($session);
    ctcl_test_webhook($processor,$session,'checkout.session.completed',$secret);
    ctcl_test_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $orders WHERE orderId = %s",$result['orderId'])) === 1 && $orderHooks === 1 && $mailCount === 1, 'Browser/webhook replay cannot duplicate order or notifications');
    ctcl_test_check($wpdb->get_var($wpdb->prepare("SELECT details FROM $table WHERE session_id = %s",$session->id)) === '{}', 'Fulfilled checkout draft removes duplicated customer details');
    $wrong = ctcl_test_fixture($data); $wrong->amount_total = 1;
    ctcl_test_check(ctcl_test_throws(function () use ($processor,$wrong) { $processor->fulfill($wrong); }), 'Paid session with wrong amount is rejected');
    $wrong = ctcl_test_fixture($data); $wrong->currency = 'eur';
    ctcl_test_check(ctcl_test_throws(function () use ($processor,$wrong) { $processor->fulfill($wrong); }), 'Paid session with wrong currency is rejected');
    $wrong = ctcl_test_fixture($data); $wrong->metadata['ctcl_fingerprint'] = 'forged';
    ctcl_test_check(ctcl_test_throws(function () use ($processor,$wrong) { $processor->fulfill($wrong); }), 'Paid session with wrong order binding is rejected');
    $wrong = ctcl_test_fixture($data); $wrong->livemode = true;
    ctcl_test_check(ctcl_test_throws(function () use ($processor,$wrong) { $processor->fulfill($wrong); }), 'Live/test environment mismatch is rejected');
    $legacy = ctcl_test_fixture($data);
    $legacyRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE session_id = %s", $legacy->id), ARRAY_A);
    $oldId = (string) time() . '123456'; $orderIds[] = $oldId;
    $legacyData = $data; $legacyData['order_id'] = $oldId; $legacyData['stripe_session_id'] = $legacy->id;
    $wpdb->update($table,array('order_id'=>$oldId),array('session_id'=>$legacy->id));
    $wpdb->insert($orders,array('orderId'=>$oldId,'orderDetail'=>wp_json_encode($legacyData),'orderStatus'=>'pending','vendorNote'=>''));
    CTCL_Stripe_Checkout::repairLegacyOrderIds();
    $newId = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM $table WHERE session_id = %s", $legacy->id)); $orderIds[] = $newId;
    $repaired = json_decode($wpdb->get_var($wpdb->prepare("SELECT orderDetail FROM $orders WHERE orderId = %s",$newId)),true);
    ctcl_test_check(strlen($newId) === 10 && gmdate('Y-m-d',(int)$newId) === gmdate('Y-m-d'), 'Early development order IDs migrate to correct dates');
    ctcl_test_check($repaired['order_id'] === $newId && $repaired['stripe_original_order_id'] === $oldId && $repaired['stripe_session_id'] === $legacy->id && !$wpdb->get_var($wpdb->prepare("SELECT orderId FROM $orders WHERE orderId = %s", $oldId)), 'Date repair preserves the payment reference and does not duplicate the order');
    echo "$checks integration checks passed.\n";
} finally {
    foreach ($attempts as $attempt) { $wpdb->delete($table,array('attempt'=>$attempt)); }
    foreach ($orderIds as $id) { $wpdb->delete($orders,array('orderId'=>$id)); }
}
