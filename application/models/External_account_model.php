<?php

use App\Config\Services;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\Query;
use CodeIgniter\Events\Events;
use MX\CI;

/**
 * @package FusionCMS
 * @author  Jesper Lindström
 * @author  Xavier Geerinck
 * @author  Elliott Robbins
 * @link    https://github.com/FusionWowCMS/FusionCMS
 */

class External_account_model extends CI_Model
{
    private BaseConnection $connection;
    private int $id;
    private string $username;
    private string $password;
    private string $email;
    private string $joindate;
    private string $last_ip;
    private string $last_login;
    private int $expansion;
    private array $account_cache;
    private string $totp_secret;

    public function __construct()
    {
        parent::__construct();

        $this->noUserExists();

        if ($this->user->getOnline()) {
            $this->initialize();
        }
    }

    public function getConnection(): BaseConnection
    {
        $this->connect();

        return $this->connection;
    }

    public function connect()
    {
        if (empty($this->connection)) {
            $this->connection = $this->load->database("account", true);
        }
    }

    public function initialize($where = false): bool
    {
        $this->connect();

        $encryption = $this->config->item('account_encryption');
        $totp_secret_name = $this->config->item('totp_secret_name');

        $query = $this->fetchAccountData($encryption, $totp_secret_name, $where);

        if (!$query)
            show_error('Database Error occurs: ' . $this->connection->error()['message'] . "<br/>Please check website database `realms.emulator` in 'field list' <b>(make sure you selected right emulator.)</b>");

        return $this->populateAccountData($query);
    }

    private function fetchAccountData($encryption, $totp_secret_name, $where): bool|BaseResult|Query
    {
        if (preg_match("/^cmangos/i", get_class($this->realms->getEmulator()))) {
            return !$where
                ? $this->connection->query(query('get_account_id'), [Services::session()->get('uid')])
                : $this->connection->query(query('get_account'), [$where]);
        }

        $columns = CI::$APP->realms->getEmulator()->getAllColumns(table('account'));
        $columns = $this->removeExtraColumnsForEncryption($columns, $encryption);

        if ($this->config->item('totp_secret')) {
            $columns['totp_secret'] = $totp_secret_name == 'totp_secret' ? 'totp_secret' : 'token_key';
        }

        $columnList = formatColumns($columns);
        $conditionColumn = !$where ? column('account', 'id') : column('account', 'username');
        $conditionValue = !$where ? Services::session()->get('uid') : $where;

        return $this->connection->query("SELECT $columnList FROM " . table('account') . " WHERE $conditionColumn = ?", [$conditionValue]);
    }

    private function removeExtraColumnsForEncryption($columns, $encryption)
    {
        switch ($encryption) {
            case 'SPH':
                if (column('account', 'verifier') && column('account', 'salt')){
                    unset($columns[column('account', 'verifier')]);
                    unset($columns[column('account', 'salt')]);
                }
                break;
            case 'SRP':
                if (column('account', 'sha_pass_hash')){
                    unset($columns[column('account', 'sha_pass_hash')]);
                }
                break;
            case 'SRP6':
                if (column('account', 'sha_pass_hash')){
                    unset($columns[column('account', 'sha_pass_hash')]);
                }
                if (column('account', 'v') && column('account', 's')){
                    unset($columns[column('account', 'v')]);
                    unset($columns[column('account', 's')]);
                }
                break;
        }
        return $columns;
    }

    private function populateAccountData($query): bool
    {
        if ($query->getNumRows() > 0) {
            $result = $query->getRowArray();
            $this->id = $result['id'];
            $this->username = $result['username'];
            $this->password = $result['verifier'] ?? strtoupper($result['sha_pass_hash']);
            $this->email = $result['email'];
            $this->joindate = $result['joindate'];
            $this->expansion = $result['expansion'];
            $this->last_ip = $result['last_ip'] ?? '';
            $this->last_login = $result['last_login'] ?? '';
            $this->totp_secret = $result['totp_secret'] ?? '';
            return true;
        }

        $this->noUserExists();

        return false;
    }

    private function noUserExists(): void
    {
        $this->account_cache = [];
        $this->id = 0;
        $this->username = 'Guest';
        $this->password = '';
        $this->email = '';
        $this->joindate = '';
        $this->expansion = 0;
        $this->last_ip = '';
        $this->last_login = '';
        $this->totp_secret = '';
    }

    /**
     * Create a new account
     *
     * @param String $username
     * @param String $password
     * @param String $email
     */
    public function createAccount(string $username, string $password, string $email)
    {
        $this->connect();

        $expansion = $this->config->item('max_expansion');

        $encryption = $this->config->item('account_encryption');

        $data = [
            column("account", "username") => strtoupper($username),
            column("account", "email") => $email,
            column("account", "expansion") => $expansion,
            column("account", "joindate") => date("Y-m-d H:i:s")
        ];

        list($hash, $data) = $this->setAccountPassword($encryption, $username, $password, $data);

        if (!preg_match("/^cmangos/i", get_class($this->realms->getEmulator())))
        {
            $data[column("account", "last_ip")] = $this->input->ip_address();
        }

        $userId = $this->connection->table(table("account"))->insert($data);

        if (preg_match("/^cmangos/i", get_class($this->realms->getEmulator())))
        {
            $ip_data = [
                'accountId' => $userId,
                'ip' => $this->input->ip_address(),
                'loginTime' => date("Y-m-d H:i:s"),
                'loginSource' => '0'
            ];

            $this->connection->table(table("account_logons"))->insert($ip_data);
        }

        $userId = $this->user->getId($username);

        // Battlenet accounts
        if ($this->config->item('battle_net')) {
            $battleData = [
                column("battlenet_accounts", "id") => $userId,
                column("battlenet_accounts", "email") => strtoupper($email),
                column("battlenet_accounts", "last_ip") => $this->input->ip_address(),
                column("battlenet_accounts", "joindate") => date("Y-m-d H:i:s")
            ];
            list($hash, $battleData) = $this->setBattleNetPassword($email, $password, $battleData);

            $this->connection->table(table("battlenet_accounts"))->insert($battleData);

            $this->connection->query("UPDATE account SET battlenet_account = $userId, battlenet_index = 1 WHERE id = $userId", [$userId]);
        }

        // Fix for TrinityCore RBAC (or any emulator with 'rbac')
        if ($this->config->item('rbac')) {
            $rbac_data = [
                'accountId'    => $userId,
                'permissionId' => 195,
                'granted'      => 1,
                'realmId'      => -1
            ];
            $this->connection->table('rbac_account_permissions')->insert($rbac_data);
        }

        $this->updateDailySignUps();
    }

    private function updateDailySignUps()
    {
        $query = $this->db->query("SELECT COUNT(*) AS `total` FROM daily_signups WHERE `date`=?", [date("Y-m-d")]);

        $row = $query->getResultArray();

        if ($row[0]['total']) {
            $this->db->query("UPDATE daily_signups SET amount = amount + 1 WHERE `date`=?", [date("Y-m-d")]);
        } else {
            $this->db->query("INSERT INTO daily_signups(`date`, amount) VALUES(?, ?)", [date("Y-m-d"), 1]);
        }
    }

    /**
     * Get the banned status
     *
     * @param Int $id
     * @return Boolean
     */
    public function getBannedStatus(int $id)
    {
        $this->connect();

        $query = $this->connection->query(query("get_banned"), [$id]);

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } elseif (query('get_ip_banned')) {
            //check if the ip is banned
            $query = $this->connection->query(query("get_ip_banned"), [$this->input->ip_address(), time()]);

            if ($query->getNumRows() > 0) {
                $row = $query->getResultArray();

                return $row[0];
            } else {
                return false;
            }
        }
    }

    /**
     * Get the rank
     *
     * @param bool|String $value
     * @param bool $isUsername
     * @return int
     */
    public function getRank(bool|string $value = false, bool $isUsername = false): int
    {
        $this->connect();

        if (!$value) {
            $value = $this->getId();
        } elseif ($isUsername) {
            $value = $this->getId($value);
        }

        $query = $this->connection->query(query("get_rank"), [$value]);

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            if ($row[0]["gmlevel"] == "") {
                $row[0]["gmlevel"] = 0;
            }

            return $row[0]["gmlevel"];
        } else {
            return 0;
        }
    }

    /**
     * Check if a username exists
     *
     * @param String $username
     * @return Boolean
     */
    public function usernameExists(string $username): bool
    {
        $this->connect();

        $count = $this->connection->table(table("account"))->where([column("account", "username") => $username])->countAllResults();

        if ($count) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get total amount of accounts
     *
     * @return Int
     */
    public function getAccountCount(): int
    {
        $this->connect();

        $query = $this->connection->query("SELECT COUNT(*) as `total` FROM " . table("account"));
        $row = $query->getResultArray();

        return $row[0]['total'];
    }

    /**
     * Check if an user id exists
     *
     * @param int $id
     * @return bool
     */
    public function userExists(int $id): bool
    {
        $this->connect();

        $count = $this->connection->table(table("account"))->where([column("account", "id") => $id])->countAllResults();

        if ($count) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if an email exists
     *
     * @param string $email
     * @return bool
     */
    public function emailExists(string $email): bool
    {
        $this->connect();

        $count = $this->connection->table(table("account"))->where([column("account", "email") => $email])->countAllResults();

        if ($count) {
            return true;
        } else {
            return false;
        }
    }

    /*
    | -------------------------------------------------------------------
    |  Setters
    | -------------------------------------------------------------------
    */
    public function setUsername($oldUsername, $newUsername)
    {
        $this->connect();

        $this->connection->table(table("account"))->where(column("account", "username"), $oldUsername)->update([column("account", "username") => $newUsername]);
    }

    public function setPassword($username, $email, $newPassword)
    {
        $this->connect();

        $builder = $this->connection->table(table("account"));

        $builder->where(column("account", "username"), $username);

        $data = [];

        if (column("account", "v") && column("account", "s") && column("account", "sessionkey")) {
            $data = [
                column("account", "v") => "",
                column("account", "s")  => "",
                column("account", "sessionkey") => "",
            ];
        }

        $encryption = $this->config->item('account_encryption');

        list($hash, $data) = $this->setAccountPassword($encryption, $username, $newPassword, $data);

        $builder->update($data);

        $userId = $this->user->getId($username);

        // Battlenet accounts
        if ($this->config->item('battle_net')) {
            $builder = $this->connection->table(table("battlenet_accounts"))->where(column("battlenet_accounts", "email"), strtoupper($email));

            $battleData = [
                column("battlenet_accounts", "last_ip") => $this->input->ip_address(),
                column("battlenet_accounts", "joindate") => date("Y-m-d H:i:s")
            ];
            list($hash, $battleData) = $this->setBattleNetPassword($email, $newPassword, $battleData);

            $builder->update($battleData);
        }

        Events::trigger('onChangePassword', $userId, $hash);
    }

    public function setEmail($username, $newEmail)
    {
        $this->connect();

        $this->connection->table(table("account"))->where(column("account", "username"), $username)->update([column("account", "email") => $newEmail]);
    }

    public function setExpansion($newExpansion, $username = false)
    {
        $this->connect();

        $builder = $this->connection->table(table("account"));

        if ($username)
        {
            // Update only the expansion column for the given username
            $builder->where(column("account", "username"), $username);
        }

        // Update the 'expansion' column for all users
        $builder->update([column("account", "expansion") => $newExpansion]);
    }

    public function setRank($userId, $newRank)
    {
        $this->connect();

        if (preg_match("/^trinity/i", get_class($this->realms->getEmulator()))) {
            $this->connection->table(table("account_access"))->where(column("account", "id"), $userId)->update([column("account_access", "SecurityLevel") => $newRank]);
        } elseif (preg_match("/^cmangos/i", get_class($this->realms->getEmulator()))) {
            $this->connection->table(table("account"))->where(column("account", "id"), $userId)->update([column("account", "gmlevel") => $newRank]);
        } else {
            $this->connection->table(table("account_access"))->where(column("account", "id"), $userId)->update([column("account_access", "gmlevel") => $newRank]);
        }
    }

    public function setLastIp($userId, $ip)
    {
        $this->connect();

        if (preg_match("/^cmangos/i", get_class($this->realms->getEmulator()))) {
            $data = [
                'accountId' => $userId,
                'ip' => $ip,
                'loginTime' => date("Y-m-d H:i:s"),
                'loginSource' => '0'
            ];

            $this->connection->table(table("account_logons"))->insert($data);
        } else {
            $this->connection->table(table("account"))->where(column("account", "id"), $userId)->update([column("account", "last_ip") => $ip]);
        }
    }

    /*
    | -------------------------------------------------------------------
    |  Getters
    | -------------------------------------------------------------------
    */
    public function getId($username = false)
    {
        if (!$username) {
            return $this->id;
        } else {
            $this->connect();

            $query = $this->connection->table(table("account"))->select(column("account", "id", true))->where(column("account", "username"), $username)->get();

            if ($query->getNumRows() > 0) {
                $result = $query->getResultArray();

                return $result[0]["id"];
            } else {
                //Return id 0
                return false;
            }
        }
    }

    /**
     * Get the username
     *
     * @param  Int $id
     * @return String
     */
    public function getUsername($id = false)
    {
        if (!$id) {
            return $this->username;
        } else {
            $this->connect();

            $query = $this->connection->table(table("account"))->select(column("account", "username", true))->where([column("account", "id") => $id])->get();

            if ($query->getNumRows() > 0) {
                $result = $query->getResultArray();

                return $result[0]["username"];
            } else {
                return "Unknown";
            }
        }
    }

    /**
     * Get the username
     *
     * @param  Int $id
     * @return String
     */
    public function getInfo($id = false, $fields = "*")
    {
        if (!$id) {
            $id = $this->id;
        }

        if ($fields != "*" && !is_array($fields)) {
            $fields = preg_replace("/ /", "", $fields);
            $fields = explode(",", $fields);
            $fields = columns("account", $fields);
        }

        $this->connect();

        $query = $this->connection->table(table("account"))->select($fields)->where([column("account", "id") => $id])->get();

        if ($query->getNumRows() > 0) {
            $result = $query->getResultArray();

            return $result[0];
        } else {
            return false;
        }
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getEmail($id = false)
    {
        if (!$id) {
            return $this->email;
        } else {
            // Check if it has been loaded already
            if (array_key_exists($id, $this->account_cache)) {
                return $this->account_cache[$id]['email'];
            } else {
                $this->connect();

                $query = $this->connection->table(table("account"))
                    ->select(column("account", "username", true) . ',' . column("account", "email") . ',' . column("account", "joindate"))
                    ->where([column("account", "id") => $id])
                    ->get();

                if ($query->getNumRows() > 0) {
                    $result = $query->getResultArray();
                    $this->account_cache[$id] = $result[0];

                    return $result[0]["email"];
                } else {
                    $this->account_cache[$id]["email"] = false;

                    return false;
                }
            }
        }
    }

    public function getIdByEmail($email = false)
    {
        if (!$email) {
            return $this->id;
        } else {
            // Check if it has been loaded already
            if (array_key_exists($email, $this->account_cache)) {
                return $this->account_cache[$email]['id'];
            } else {
                $this->connect();

                ;
                $query = $this->connection->table(table("account"))->select(column("account", "id"))->where([column("account", "email") => $email])->get();

                if ($query->getNumRows() > 0) {
                    $result = $query->getResultArray();
                    $this->account_cache[$email] = $result[0];

                    return $result[0]["id"];
                } else {
                    $this->account_cache[$email]["id"] = false;

                    return false;
                }
            }
        }
    }

    public function getJoinDate(): string
    {
        return $this->joindate;
    }

    public function getLastIp(): string
    {
        return $this->last_ip;
    }

    public function getLastLogin(): string
    {
        return $this->last_login;
    }

    public function getExpansion(): int
    {
        return $this->expansion;
    }

    public function getTotpSecret(): string
    {
        return $this->totp_secret;
    }

    /**
     * @param string|null $encryption
     * @param $username
     * @param $newPassword
     * @param array $data
     * @return array
     */
    private function setAccountPassword(?string $encryption, $username, $newPassword, array $data): array
    {
        if ($encryption == 'SRP6') {
            $hash = $this->crypto->SRP6($username, $newPassword);
            $data[column("account", "salt")] = $hash["salt"];
            $data[column("account", "verifier")] = $hash["verifier"];
        } else if ($encryption == 'SRP') {
            $hash = $this->crypto->SRP($username, $newPassword);
            $data[column("account", "salt")] = $hash["salt"];
            $data[column("account", "verifier")] = $hash["verifier"];
        } else {
            $hash = $this->crypto->SHA_PASS_HASH($username, $newPassword);
            $data[column("account", "sha_pass_hash")] = $hash["verifier"];
        }
        return array($hash, $data);
    }

    /**
     * @param $email
     * @param $newPassword
     * @param array $battleData
     * @return array
     */
    private function setBattleNetPassword($email, $newPassword, array $battleData): array
    {
        if ($this->config->item('battle_net_encryption') == 'SRP6_V2') {
            $hash = $this->crypto->BnetSRP6_V2($email, $newPassword);
            $battleData['srp_version'] = 2;
            $battleData[column("battlenet_accounts", "salt")] = $hash["salt"];
            $battleData[column("battlenet_accounts", "verifier")] = $hash["verifier"];
        } else if ($this->config->item('battle_net_encryption') == 'SRP6_V1') {
            $hash = $this->crypto->BnetSRP6_V1($email, $newPassword);
            $battleData['srp_version'] = 1;
            $battleData[column("battlenet_accounts", "salt")] = $hash["salt"];
            $battleData[column("battlenet_accounts", "verifier")] = $hash["verifier"];
        } else {
            $hash = $this->crypto->SHA_PASS_HASH_V2($email, $newPassword);
            $battleData[column("battlenet_accounts", "sha_pass_hash")] = $hash["verifier"];
        }
        return array($hash, $battleData);
    }

    /**
     * Authenticate a Battle.net account by email/password (website login when battle_net is enabled)
     * and return the linked game accounts.
     *
     * @param string $email
     * @param string $password
     * @return array|false  ['bnet_id'=>int, 'accounts'=>[['id','username','index','label'], ...]] or false
     */
    public function loginByBattlenet(string $email, string $password)
    {
        $this->connect();

        $emailUpper = strtoupper($email);

        $bnet = $this->connection->table(table("battlenet_accounts"))
            ->where(column("battlenet_accounts", "email"), $emailUpper)
            ->get()->getRowArray();

        if (!$bnet) {
            return false;
        }

        $encryption    = $this->config->item('battle_net_encryption');
        $storedSalt     = $bnet[column("battlenet_accounts", "salt")] ?? null;
        $storedVerifier = $bnet[column("battlenet_accounts", "verifier")] ?? null;

        if ($encryption == 'SRP6_V2') {
            $hash = $this->crypto->BnetSRP6_V2($email, $password, $storedSalt);
            $ok   = is_string($storedVerifier) && hash_equals($storedVerifier, $hash['verifier']);
        } else if ($encryption == 'SRP6_V1') {
            $hash = $this->crypto->BnetSRP6_V1($email, $password, $storedSalt);
            $ok   = is_string($storedVerifier) && hash_equals($storedVerifier, $hash['verifier']);
        } else { // SPH
            $hash      = $this->crypto->SHA_PASS_HASH_V2($email, $password);
            $storedSha = $bnet[column("battlenet_accounts", "sha_pass_hash")] ?? '';
            $ok        = strtoupper((string)$storedSha) === strtoupper((string)$hash['verifier']);
        }

        if (!$ok) {
            return false;
        }

        $bnetId = $bnet[column("battlenet_accounts", "id")];

        $rows = $this->connection->table(table("account"))
            ->select(column("account", "id") . ', ' . column("account", "username") . ', battlenet_index')
            ->where('battlenet_account', $bnetId)
            ->orderBy('battlenet_index', 'ASC')
            ->get()->getResultArray();

        $accounts = [];
        foreach ($rows as $r) {
            $accounts[] = [
                'id'       => $r[column("account", "id")],
                'username' => $r[column("account", "username")],
                'index'    => $r['battlenet_index'],
                'label'    => $bnetId . '#' . $r['battlenet_index'],
            ];
        }

        return [
            'bnet_id'  => $bnetId,
            'accounts' => $accounts,
        ];
    }

    /**
     * Get the Battle.net id and email linked to a game account.
     *
     * @param int $accountId
     * @return array|false ['bnet_id'=>int, 'email'=>string] or false if not linked
     */
    public function getBattlenetInfo($accountId)
    {
        $this->connect();

        $row = $this->connection->table(table("account"))
            ->select('battlenet_account')
            ->where(column("account", "id"), $accountId)
            ->get()->getRowArray();

        if (!$row || empty($row['battlenet_account'])) {
            return false;
        }

        $bnetId = $row['battlenet_account'];

        $bnet = $this->connection->table(table("battlenet_accounts"))
            ->select(column("battlenet_accounts", "email"))
            ->where(column("battlenet_accounts", "id"), $bnetId)
            ->get()->getRowArray();

        return [
            'bnet_id' => $bnetId,
            'email'   => $bnet ? $bnet[column("battlenet_accounts", "email")] : '',
        ];
    }

    /**
     * Next available game-account index (the #N part) for a Battle.net account.
     *
     * @param int $bnetId
     * @return int
     */
    public function getNextGameIndex($bnetId): int
    {
        $this->connect();

        $row = $this->connection->table(table("account"))
            ->selectMax('battlenet_index', 'maxidx')
            ->where('battlenet_account', $bnetId)
            ->get()->getRowArray();

        return ($row && $row['maxidx'] !== null) ? ((int)$row['maxidx'] + 1) : 1;
    }

    /**
     * Create an additional game account ("bnetId#index") under an existing Battle.net account.
     * No email/password is requested: the account name is generated and a random game password is set
     * (website login is handled through the Battle.net email).
     *
     * @param int $bnetId
     * @param string $email
     * @return array ['username'=>string, 'index'=>int, 'label'=>string]
     */
    public function createGameAccount($bnetId, string $email): array
    {
        $this->connect();

        $encryption = $this->config->item('account_encryption');
        $expansion  = $this->config->item('max_expansion');
        $nextIndex  = $this->getNextGameIndex($bnetId);
        $username   = $bnetId . '#' . $nextIndex;
        $password   = bin2hex(random_bytes(8));

        $data = [
            column("account", "username") => $username,
            column("account", "email")    => $email,
            column("account", "expansion") => $expansion,
            column("account", "joindate") => date("Y-m-d H:i:s"),
        ];

        list($hash, $data) = $this->setAccountPassword($encryption, $username, $password, $data);

        if (!preg_match("/^cmangos/i", get_class($this->realms->getEmulator()))) {
            $data[column("account", "last_ip")] = $this->input->ip_address();
        }

        $data['battlenet_account'] = $bnetId;
        $data['battlenet_index']   = $nextIndex;

        $this->connection->table(table("account"))->insert($data);

        return [
            'username' => $username,
            'index'    => $nextIndex,
            'label'    => $username,
        ];
    }

    /*
    | -------------------------------------------------------------------
    |  Phone number (stored on battlenet_accounts.phone, per Battle.net account)
    | -------------------------------------------------------------------
    */

    /**
     * Resolve the Battle.net account id linked to a game account.
     */
    private function bnetIdOfAccount($accountId): int
    {
        $this->connect();
        $row = $this->connection->query(
            "SELECT battlenet_account FROM " . table('account') . " WHERE " . column('account', 'id') . " = ? LIMIT 1",
            [$accountId]
        )->getRowArray();

        return (int) ($row['battlenet_account'] ?? 0);
    }

    /**
     * Get the phone of the Battle.net account linked to a game account ('' if none).
     */
    public function getPhoneByAccount($accountId): string
    {
        $this->connect();
        $row = $this->connection->query(
            "SELECT b.phone AS phone FROM " . table('account') . " a JOIN " . table('battlenet_accounts') . " b ON b." . column('battlenet_accounts', 'id') . " = a.battlenet_account WHERE a." . column('account', 'id') . " = ? LIMIT 1",
            [$accountId]
        )->getRowArray();

        return ($row && !empty($row['phone'])) ? $row['phone'] : '';
    }

    /**
     * Set the phone on the Battle.net account linked to a game account.
     */
    public function setPhoneByAccount($accountId, string $phone): bool
    {
        $bnetId = $this->bnetIdOfAccount($accountId);
        if (!$bnetId) {
            return false;
        }

        $this->connection->query(
            "UPDATE " . table('battlenet_accounts') . " SET phone = ? WHERE " . column('battlenet_accounts', 'id') . " = ?",
            [$phone, $bnetId]
        );

        return true;
    }

    /**
     * Whether a phone is already registered on another Battle.net account.
     */
    public function phoneInUse(string $phone, $exceptAccountId = 0): bool
    {
        if ($phone === '') {
            return false;
        }

        $this->connect();

        $sql    = "SELECT COUNT(*) AS c FROM " . table('battlenet_accounts') . " WHERE phone = ?";
        $params = [$phone];

        if ($exceptAccountId) {
            $exceptBnet = $this->bnetIdOfAccount($exceptAccountId);
            if ($exceptBnet) {
                $sql     .= " AND " . column('battlenet_accounts', 'id') . " != ?";
                $params[] = $exceptBnet;
            }
        }

        $row = $this->connection->query($sql, $params)->getRowArray();

        return ((int) ($row['c'] ?? 0)) > 0;
    }
}
