<?php

use MX\MX_Controller;

/**
 * SSO Controller — punto de entrada de la tienda/soporte del cliente WoW.
 *
 * El cliente (Wow.exe parcheado) abre:
 *     https://inna.cl/login/sso?token=<SSO>&ref=<destino>
 *
 * Aqui validamos el token contra auth.battlepay_sso (lo genera el worldserver en
 * CMSG_BATTLE_PAY_OPEN_CHECKOUT) y redirigimos a la URL configurada en worldserver.conf
 * (BattlePay.StoreUrl). Asi la ruta se cambia desde el .conf sin reparchear el cliente.
 *
 * @property CI_DB_query_builder $authdb
 */
class Sso extends MX_Controller
{
    // Ruta al worldserver.conf (fuente unica de verdad de URLs y datos de BD)
    private string $confPath = 'C:/Users/poved/Desktop/TrinityCore/build/bin/Release/worldserver.conf';

    public function index()
    {
        $token = (string) $this->input->get('token');
        if ($token === '') {
            show_error('SSO: falta token', 400);
            return;
        }

        // BD auth (battlepay_sso, account): usamos el grupo 'account' de FusionCMS, ya
        // configurado al auth real :3306 (misma BD que usa loginVerified()).
        $authdb = $this->load->database('account', true);

        // --- validar token (no expirado) ---
        $row = $authdb->query(
            'SELECT account_id FROM battlepay_sso WHERE token = ? AND expiry_at > UNIX_TIMESTAMP() LIMIT 1',
            [$token]
        )->getRow();

        if (!$row) {
            show_error('SSO: token invalido o caducado', 403);
            return;
        }

        $accountId = (int) $row->account_id;

        // --- auto-login web: el token YA autentica la cuenta del juego ---
        // resolvemos el nombre de cuenta y abrimos sesion sin password via loginVerified().
        $acc = $authdb->query('SELECT username FROM account WHERE id = ? LIMIT 1', [$accountId])->getRow();
        if ($acc && isset($acc->username)) {
            $this->load->library('user');                  // idempotente
            $this->user->loginVerified($acc->username);     // setea la sesion (uid, etc.)
        }

        // el token caduca a 4h (lo gestiona el worldserver); no lo borramos aqui para que
        // recargas/back del webview no rompan con 403.

        // --- destino segun 'dest' (tienda por defecto, o soporte) ---
        // Ambas rutas salen del worldserver.conf -> cambiar sin reparchear Wow.
        $dest = (string) $this->input->get('dest');
        if ($dest === 'support' || $dest === 'soporte') {
            $target = trim($this->confValue('Support.LandingUrl', 'https://www.inna.cl/soporte'), '"');
        } else {
            $target = trim($this->confValue('BattlePay.StoreUrl', 'https://www.inna.cl/tienda'), '"');
        }
        redirect($target);
    }

    /**
     * Lee un valor "Clave = valor" del worldserver.conf (ignora comentarios y comillas).
     */
    private function confValue(string $key, string $default): string
    {
        if (!is_readable($this->confPath)) {
            return $default;
        }
        foreach (file($this->confPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = ltrim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            if (trim(substr($line, 0, $pos)) === $key) {
                return trim(trim(substr($line, $pos + 1)), " \t\"");
            }
        }
        return $default;
    }
}
