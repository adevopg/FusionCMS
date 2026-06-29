<?php

use CodeIgniter\Events\Events;
use MX\MX_Controller;

/**
 * Admin_battlepay Controller Class
 *
 * Simple CRUD over the shared BattlePay catalog (table `battlepay_product` on the
 * realm account/auth database) which the web store reads when `store_use_battlepay`
 * is enabled. Lets staff add/edit/remove/enable products and set their price (EUR).
 *
 * @property Items_model $items_model
 */
class Admin_battlepay extends MX_Controller
{
    public function __construct()
    {
        $this->load->library('administrator');
        $this->load->model('items_model');

        parent::__construct();

        $this->load->config('store');

        requirePermission("canViewItems");
    }

    /**
     * List all BattlePay products.
     */
    public function index()
    {
        $this->administrator->setTitle(lang('battlepay', 'store'));

        $data = [
            'url'        => $this->template->page_url,
            'items'      => $this->items_model->getBattlePayItems(),
            'categories' => $this->items_model->getBattlePayCategories(),
        ];

        $output  = $this->template->loadPage("admin_battlepay.tpl", $data);
        $content = $this->administrator->box(lang('battlepay', 'store'), $output);

        $this->administrator->view($content, false, "modules/store/js/admin_battlepay.js");
    }

    /**
     * Show the "add product" form.
     */
    public function add()
    {
        requirePermission("canAddItems");

        $this->administrator->setTitle(lang('battlepay_add', 'store'));

        $data = [
            'url'        => $this->template->page_url,
            'item'       => false,
            'categories' => $this->items_model->getBattlePayCategories(),
            'action'     => $this->template->page_url . 'store/admin_battlepay/create',
        ];

        $output  = $this->template->loadPage("admin_battlepay_form.tpl", $data);
        $content = $this->administrator->box('<a href="' . $this->template->page_url . 'store/admin_battlepay">' . lang('battlepay', 'store') . '</a> &rarr; ' . lang('battlepay_add', 'store'), $output);

        $this->administrator->view($content, false, "modules/store/js/admin_battlepay.js");
    }

    /**
     * Create a product (POST).
     */
    public function create()
    {
        requirePermission("canAddItems");

        $data = $this->collect();

        if ($data === false) {
            die(lang('battlepay_name_required', 'store'));
        }

        $this->items_model->addBattlePay($data);
        $this->cache->delete('store_items');

        $this->dblogger->createLog("admin", "add", "BattlePay product added", ['Product' => $data['name']]);
        Events::trigger('onAddBattlePayProduct', $data);

        die('yes');
    }

    /**
     * Show the "edit product" form.
     *
     * @param int|bool $id
     */
    public function edit($id = false)
    {
        requirePermission("canEditItems");

        if (!is_numeric($id) || !$id) {
            die();
        }

        $item = $this->items_model->getBattlePayItem($id);

        if (!$item) {
            show_error(lang('battlepay_no_product', 'store') . ' ' . $id, 400);
            die();
        }

        $this->administrator->setTitle($item['name']);

        $data = [
            'url'        => $this->template->page_url,
            'item'       => $item,
            'categories' => $this->items_model->getBattlePayCategories(),
            'action'     => $this->template->page_url . 'store/admin_battlepay/save/' . $id,
        ];

        $output  = $this->template->loadPage("admin_battlepay_form.tpl", $data);
        $content = $this->administrator->box('<a href="' . $this->template->page_url . 'store/admin_battlepay">' . lang('battlepay', 'store') . '</a> &rarr; ' . $item['name'], $output);

        $this->administrator->view($content, false, "modules/store/js/admin_battlepay.js");
    }

    /**
     * Save an edited product (POST).
     *
     * @param int|bool $id
     */
    public function save($id = false)
    {
        requirePermission("canEditItems");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $data = $this->collect();

        if ($data === false) {
            die(lang('battlepay_name_required', 'store'));
        }

        $this->items_model->editBattlePay($id, $data);
        $this->cache->delete('store_items');

        $this->dblogger->createLog("admin", "edit", "BattlePay product edited", ['ID' => $id, 'Product' => $data['name']]);
        Events::trigger('onEditBattlePayProduct', $id, $data);

        die('yes');
    }

    /**
     * Delete a product.
     *
     * @param int|bool $id
     */
    public function delete($id = false)
    {
        requirePermission("canRemoveItems");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $this->items_model->deleteBattlePay($id);
        $this->cache->delete('store_items');

        $this->dblogger->createLog("admin", "delete", "BattlePay product deleted", ['ID' => $id]);
        Events::trigger('onDeleteBattlePayProduct', $id);

        die('yes');
    }

    /**
     * Toggle the enabled flag of a product.
     *
     * @param int|bool $id
     */
    public function toggle($id = false)
    {
        requirePermission("canEditItems");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $this->items_model->toggleBattlePay($id);
        $this->cache->delete('store_items');

        die('yes');
    }

    /**
     * Collect and validate the product fields from POST.
     *
     * @return array|false
     */
    private function collect()
    {
        $name = trim((string) $this->input->post('name'));

        if ($name === '') {
            return false;
        }

        // Icon: accept a WoW icon name (e.g. inv_misc_gift_02) or a full image URL.
        $icon = trim((string) $this->input->post('icon'));
        if ($icon !== '' && !preg_match('#^https?://#i', $icon)) {
            // strip a pasted ".jpg"/".png" and lowercase a plain icon name
            $icon = strtolower(preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $icon));
        }

        return [
            'name'             => $name,
            'description'      => (string) $this->input->post('description'),
            'category'         => trim((string) $this->input->post('category')),
            'icon'             => $icon,
            'delivery_command' => (string) $this->input->post('delivery_command'),
            'price'            => (int) $this->input->post('price'),
            'enabled'          => $this->input->post('enabled') ? 1 : 0,
        ];
    }
}
