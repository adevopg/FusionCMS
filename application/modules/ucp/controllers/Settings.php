<?php

use App\Config\Services;
use CodeIgniter\Events\Events;
use MX\MX_Controller;

/**
 * Settings Controller Class
 * @property settings_model $settings_model settings_model Class
 */
class Settings extends MX_Controller
{
    public function __construct()
    {
        //Call the constructor of MX_Controller
        parent::__construct();

        $this->load->config('settings');
        $this->load->config('links');

        //Make sure that we are logged in
        $this->user->userArea();

        $this->load->library('form_validation');

        $this->load->helper('cookie');

        $this->load->config('twilio');
        $this->load->library('twilio');
        $this->load->model('external_account_model');
    }

    /**
     * Current verified phone for the logged-in account (or '').
     */
    private function currentPhone(): string
    {
        return $this->external_account_model->getPhoneByAccount($this->user->getId());
    }

    /**
     * Mask a phone for display: +34******66
     */
    private function maskPhone(string $phone): string
    {
        if ($phone === '') {
            return '';
        }
        $len = strlen($phone);
        if ($len <= 5) {
            return $phone;
        }
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 5)) . substr($phone, -2);
    }

    public function index()
    {
        requirePermission("canUpdateAccountSettings");

        clientLang("nickname_error", "ucp");
        clientLang("location_error", "ucp");
        clientLang("pw_doesnt_match", "ucp");
        clientLang("changes_saved", "ucp");
        clientLang("invalid_pw", "ucp");
        clientLang("nickname_taken", "ucp");
        clientLang("invalid_language", "ucp");

        $this->template->setTitle(lang("settings", "ucp"));

        clientLang("phone_change_new", "ucp");
        clientLang("phone_code_old", "ucp");
        clientLang("phone_code_new", "ucp");
        clientLang("phone_changed", "ucp");
        clientLang("phone_send_to_old", "ucp");

        $settings_data = [
            'nickname' => $this->user->getNickname(),
            'twilio_enabled' => $this->twilio->enabled(),
            'phone_masked' => $this->maskPhone($this->currentPhone()),
            'location' => $this->internal_user_model->getLocation(),
            'show_language_chooser' => $this->config->item('show_language_chooser'),
            'userLanguage' => $this->language->getLanguage(),
            "avatar" => $this->user->getAvatar($this->user->getId()),

            "config" => [
                "vote" => $this->config->item('ucp_vote'),
                "donate" => $this->config->item('ucp_donate'),
                "store" => $this->config->item('ucp_store'),
                "settings" => $this->config->item('ucp_settings'),
                "security" => $this->config->item('ucp_security'),
                "teleport" => $this->config->item('ucp_teleport'),
                "admin" => $this->config->item('ucp_admin'),
                "gm" => $this->config->item('ucp_mod')
            ]
        ];

        if ($this->config->item('show_language_chooser')) {
            $settings_data['languages'] = $this->language->getAllLanguages();
        }

        $data = [
            "module" => "default",
            "headline" => breadcrumb([
                            "ucp" => lang("ucp"),
                            "ucp/settings" => lang("settings", "ucp")
            ]),
            "content" => $this->template->loadPage("settings.tpl", $settings_data)
        ];

        $page = $this->template->loadPage("page.tpl", $data);

        //Load the template form
        $this->template->view($page, "modules/ucp/css/ucp.css", "modules/ucp/js/settings.js");
    }

    /* ----------------------------------------------------------------
     |  Change phone number — requires SMS of BOTH the current and the
     |  new number (Twilio Verify). AJAX, step by step.
     | ---------------------------------------------------------------- */

    private function phoneInUse(string $phone): bool
    {
        return $this->external_account_model->phoneInUse($phone, $this->user->getId());
    }

    private function ajaxGuard(): void
    {
        if (!$this->input->is_ajax_request()) {
            die('No direct script access allowed');
        }
        if (!$this->twilio->enabled()) {
            die(json_encode(['ok' => false, 'error' => lang('phone_disabled', 'ucp')]));
        }
    }

    /** Step 1: send a code to the CURRENT phone. */
    public function phoneSendOld()
    {
        $this->ajaxGuard();

        $current = $this->currentPhone();
        if ($current === '') {
            die(json_encode(['ok' => false, 'error' => lang('phone_no_current', 'ucp')]));
        }

        if (!$this->twilio->start($current)) {
            die(json_encode(['ok' => false, 'error' => lang('phone_send_failed', 'ucp')]));
        }

        Services::session()->set('chgphone_old_ok', false);
        die(json_encode(['ok' => true]));
    }

    /** Step 2: verify the CURRENT phone code. */
    public function phoneVerifyOld()
    {
        $this->ajaxGuard();

        $current = $this->currentPhone();
        if ($current === '' || !$this->twilio->check($current, (string) $this->input->post('code'))) {
            die(json_encode(['ok' => false, 'error' => lang('phone_invalid_code', 'ucp')]));
        }

        Services::session()->set('chgphone_old_ok', true);
        die(json_encode(['ok' => true]));
    }

    /** Step 3: send a code to the NEW phone. */
    public function phoneSendNew()
    {
        $this->ajaxGuard();

        if (!Services::session()->get('chgphone_old_ok')) {
            die(json_encode(['ok' => false, 'error' => lang('phone_need_old', 'ucp')]));
        }

        $new = $this->twilio->normalize((string) $this->input->post('phone'));

        if (strlen(preg_replace('/\D/', '', $new)) < 6) {
            die(json_encode(['ok' => false, 'error' => lang('phone_bad', 'ucp')]));
        }
        if ($new === $this->currentPhone()) {
            die(json_encode(['ok' => false, 'error' => lang('phone_same', 'ucp')]));
        }
        if ($this->phoneInUse($new)) {
            die(json_encode(['ok' => false, 'error' => lang('phone_in_use', 'ucp')]));
        }
        if (!$this->twilio->start($new)) {
            die(json_encode(['ok' => false, 'error' => lang('phone_send_failed', 'ucp')]));
        }

        Services::session()->set('chgphone_new', $new);
        die(json_encode(['ok' => true]));
    }

    /** Step 4: verify the NEW phone code and save it. */
    public function phoneVerifyNew()
    {
        $this->ajaxGuard();

        $new = (string) Services::session()->get('chgphone_new');

        if (!Services::session()->get('chgphone_old_ok') || $new === '') {
            die(json_encode(['ok' => false, 'error' => lang('phone_need_old', 'ucp')]));
        }
        if (!$this->twilio->check($new, (string) $this->input->post('code'))) {
            die(json_encode(['ok' => false, 'error' => lang('phone_invalid_code', 'ucp')]));
        }
        if ($this->phoneInUse($new)) {
            die(json_encode(['ok' => false, 'error' => lang('phone_in_use', 'ucp')]));
        }

        $this->external_account_model->setPhoneByAccount($this->user->getId(), $new);

        Services::session()->remove(['chgphone_old_ok', 'chgphone_new']);
        $this->dblogger->createLog("user", "settings", "Changed phone number");

        die(json_encode(['ok' => true, 'phone_masked' => $this->maskPhone($new)]));
    }

    public function submit()
    {
        if ($this->input->method() !== 'post') {
            show_error('Invalid request', 403);
        }

        $this->form_validation->set_rules('old_password', lang("old_password", "ucp"), 'trim|required|min_length[6]|max_length[16]');
        $this->form_validation->set_rules('new_password', lang("new_password", "ucp"), 'trim|required|min_length[6]|max_length[16]');

        if ($this->form_validation->run() === false) {
            $response = [
                'status'  => 'error',
                'message' => validation_errors()
            ];
            die(json_encode($response));
        }

        $oldPassword = $this->input->post('old_password');
        $newPassword = $this->input->post('new_password');

        // Get the current password
        $currentPassword = $this->user->getPassword();

        // Generate the verifier from the entered old password
        $passwordHash = $this->user->getAccountPassword($this->user->getUsername(), $oldPassword);

        // Check if passwords match
        if (strtoupper($currentPassword) === strtoupper($passwordHash["verifier"])) {
            $this->user->setPassword($newPassword);

            delete_cookie("fcms_username");
            delete_cookie("fcms_password");

            Services::session()->destroy();

            $response = [
                'status'  => 'success',
                'message' => lang("changes_saved", "ucp")
            ];
        } else {
            $response = [
                'status'  => 'error',
                'message' => lang("invalid_pw", "ucp")
            ];
        }

        die(json_encode($response));
    }

    public function submitInfo()
    {
        $this->load->model("settings_model");

        // Gather the values

        $nickname = $this->input->post("nickname");
        $location = $this->input->post("location");

        if (!is_string($nickname) || !is_string($location)) {
            die("4");
        }

        $values = array(
            // Update sanitization according to CMS standards.
            'nickname' => $this->template->format($nickname),
            'location' => $this->template->format($location),
        );

        // Change language
        if ($this->config->item('show_language_chooser')) {
            $values['language'] = $this->input->post("language");

            if (!is_dir("application/language/" . $values['language'])) {
                die("3");
            } else {
                $this->user->setLanguage($values['language']);

                Events::trigger('onSetLanguage', $this->user->getId(), $values['language']);
            }
        }

        // Remove the nickname field if it wasn't changed
        if ($values['nickname'] == $this->user->getNickname()) {
            $values = array('location' => $location);
        } elseif (
            strlen($values['nickname']) < 4
            || strlen($values['nickname']) > 14
            || !preg_match("/[A-Za-z0-9]*/", $values['nickname'])
        ) {
            die(lang("nickname_error", "ucp"));
        } elseif ($this->internal_user_model->nicknameExists($values['nickname'])) {
            die("2");
        }

        if (strlen($values['location']) > 32 && !ctype_alpha($values['location'])) {
            die(lang("location_error", "ucp"));
        }

        $this->settings_model->saveSettings($values);

        Events::trigger('onSaveSettingsAccount', $this->user->getId(), $values);

        die("1");
    }
}
