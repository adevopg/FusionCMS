<?php

use CodeIgniter\Events\Events;
use MX\MX_Controller;

/**
 * SumUp Controller — real-money checkout for BattlePay products.
 *
 * Endpoints (AJAX):
 *   POST store/sumup/create   { cart: [{id}, ...] }  -> { id, reference, amount }
 *   POST store/sumup/confirm  { checkout_id }        -> { status }
 *
 * The card is charged client-side by the SumUp Card Widget; we never see card data.
 * As SumUp has no webhooks, payment status is verified by polling GET /checkouts/{id}.
 *
 * @property store_model $store_model
 * @property sumup_model $sumup_model
 */
class Sumup extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->user->userArea();

        $this->load->model("store_model");
        $this->load->model("sumup_model");
        $this->load->config("store");
        $this->load->config("sumup");

        requirePermission("view");
    }

    private function enabled(): bool
    {
        return (bool) $this->config->item('sumup_enabled') && (bool) $this->config->item('store_use_battlepay');
    }

    /**
     * Create a SumUp checkout for the given cart and store a PENDING order.
     */
    public function create()
    {
        if (!$this->input->is_ajax_request()) {
            die('No direct script access allowed');
        }

        if (!$this->enabled()) {
            die(json_encode(['error' => lang('sumup_disabled', 'store')]));
        }

        $cart = json_decode((string) $this->input->post('cart'), true);

        if (!is_array($cart) || count($cart) === 0) {
            die(json_encode(['error' => lang('empty_cart', 'store')]));
        }

        $amount  = 0.0;
        $ids     = [];
        $names   = [];
        $realmId = 0;

        foreach ($cart as $entry) {
            $id   = (int) ($entry['id'] ?? 0);
            $item = $this->store_model->getItem($id);

            if (!$item) {
                die(json_encode(['error' => 'Invalid item: ' . $id]));
            }

            // BattlePay price is stored as euros in dp_price
            $amount += (float) $item['dp_price'];
            $ids[]   = $id;
            $names[] = $item['name'];
            $realmId = (int) $item['realm'];
        }

        if ($amount <= 0) {
            die(json_encode(['error' => lang('sumup_zero_amount', 'store')]));
        }

        // Target character (for in-game delivery). Must belong to the buyer.
        $character = trim((string) $this->input->post('character'));
        if ($character === '') {
            die(json_encode(['error' => lang('sumup_need_character', 'store')]));
        }
        $realm = $this->realms->getRealm($realmId);
        if (!$realm || !$realm->getCharacters()->characterBelongsToAccount($character, $this->user->getId())) {
            die(json_encode(['error' => lang('sumup_bad_character', 'store')]));
        }

        $reference = 'BP-' . $this->user->getId() . '-' . time() . '-' . bin2hex(random_bytes(3));
        $desc      = mb_substr(implode(', ', $names), 0, 120);

        $checkout = $this->sumup_model->createCheckout($reference, $amount, $desc);

        if (!$checkout || empty($checkout['id'])) {
            $msg = $checkout['error'] ?? ($checkout['message'] ?? lang('sumup_create_failed', 'store'));
            die(json_encode(['error' => $msg]));
        }

        // Record a pending order (cms database, default connection)
        $this->db->table('battlepay_orders')->insert([
            'account_id'         => $this->user->getId(),
            'checkout_reference' => $reference,
            'checkout_id'        => $checkout['id'],
            'product_ids'        => implode(',', $ids),
            'character'          => $character,
            'amount'             => round($amount, 2),
            'currency'           => $this->config->item('sumup_currency'),
            'status'             => 'PENDING',
        ]);

        die(json_encode([
            'id'        => $checkout['id'],
            'reference' => $reference,
            'amount'    => round($amount, 2),
            'currency'  => $this->config->item('sumup_currency'),
        ]));
    }

    /**
     * Verify a checkout via the API and mark the order paid.
     */
    public function confirm()
    {
        if (!$this->input->is_ajax_request()) {
            die('No direct script access allowed');
        }

        $checkoutId = (string) $this->input->post('checkout_id');

        if ($checkoutId === '') {
            die(json_encode(['error' => 'Missing checkout id']));
        }

        // The order must belong to the current user
        $order = $this->db->table('battlepay_orders')
            ->where(['checkout_id' => $checkoutId, 'account_id' => $this->user->getId()])
            ->get()->getRowArray();

        if (!$order) {
            die(json_encode(['error' => 'Order not found']));
        }

        $checkout = $this->sumup_model->getCheckout($checkoutId);
        $status   = strtoupper((string) ($checkout['status'] ?? 'UNKNOWN'));

        // Guard against amount tampering
        $paidAmount = (float) ($checkout['amount'] ?? 0);
        if ($status === 'PAID' && abs($paidAmount - (float) $order['amount']) > 0.001) {
            $status = 'FAILED';
        }

        if ($status === 'PAID' && $order['status'] !== 'PAID') {
            $this->db->table('battlepay_orders')->where('id', $order['id'])->update(['status' => 'PAID']);
            $this->dblogger->createLog("user", "store", "BattlePay SumUp payment", ['Order' => $order['checkout_reference'], 'Amount' => $order['amount'] . ' ' . $order['currency']]);
            Events::trigger('onBattlePayPaid', $order);

            // Deliver in-game (SOAP). Records the outcome on the order.
            $this->deliver($order);
        } elseif (in_array($status, ['FAILED', 'EXPIRED'], true)) {
            $this->db->table('battlepay_orders')->where('id', $order['id'])->update(['status' => $status]);
        }

        die(json_encode([
            'status'  => $status,
            'paid'    => $status === 'PAID',
            'message' => $status === 'PAID' ? lang('sumup_paid', 'store') : lang('sumup_pending', 'store'),
        ]));
    }

    /**
     * Deliver the purchased BattlePay products in-game via the realm's SOAP console.
     * Each product's `delivery_command` is run with $character / $account substituted.
     * Failures are recorded on the order (delivered stays 0) so they can be retried.
     */
    private function deliver(array $order): void
    {
        $productIds = array_filter(array_map('intval', explode(',', (string) $order['product_ids'])));
        $character  = (string) $order['character'];
        $results    = [];
        $allOk      = true;

        foreach ($productIds as $pid) {
            $item = $this->store_model->getItem($pid);
            $cmd  = $item['delivery_command'] ?? '';

            if (trim($cmd) === '') {
                $results[] = "#$pid: no delivery command (skipped)";
                continue;
            }

            $cmd = str_replace(['$character', '$account'], [$character, $this->user->getUsername()], $cmd);

            [$ok, $msg] = $this->runConsole((int) $item['realm'], $cmd);
            $allOk = $allOk && $ok;
            $results[] = "#$pid: " . ($ok ? 'OK' : 'FAIL') . " ($msg)";
        }

        $this->db->table('battlepay_orders')->where('id', $order['id'])->update([
            'delivered'       => $allOk ? 1 : 0,
            'delivery_result' => implode(' | ', $results),
        ]);
    }

    /**
     * Run a console command on a realm's worldserver via SOAP (no die() on error).
     *
     * @return array{0:bool,1:string} [success, message]
     */
    private function runConsole(int $realmId, string $command): array
    {
        $realm = $this->db->table('realms')->where('id', $realmId)->get()->getRowArray();
        if (!$realm) {
            return [false, 'realm not found'];
        }

        if (!class_exists('SoapClient')) {
            return [false, 'SOAP extension missing'];
        }

        try {
            $client = new SoapClient(null, [
                'location'           => 'http://' . $realm['hostname'] . ':' . $realm['console_port'],
                'uri'                => 'urn:TC',
                'login'              => $realm['console_username'],
                'password'           => $realm['console_password'],
                'connection_timeout' => 8,
                'exceptions'         => true,
            ]);

            $response = $client->executeCommand(new SoapParam($command, 'command'));

            return [true, is_string($response) ? trim($response) : 'sent'];
        } catch (Throwable $e) {
            log_message('error', 'BattlePay SOAP delivery failed: ' . $e->getMessage());
            return [false, $e->getMessage()];
        }
    }
}
