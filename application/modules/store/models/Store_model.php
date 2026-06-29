<?php

class Store_model extends CI_Model
{
    /**
     * Whether the store should read the shared in-game BattlePay catalog
     * (table `battlepay_product` on the realm account/auth database).
     */
    private function useBattlePay(): bool
    {
        $this->load->config('store');

        return (bool) $this->config->item('store_use_battlepay');
    }

    /**
     * Connection to the realm account/auth database where battlepay_product lives.
     */
    private function battlePayDb()
    {
        return $this->load->database('account', true);
    }

    /**
     * Stable numeric group id derived from a BattlePay category name.
     */
    private function categoryGroupId(string $category): int
    {
        return crc32($category) & 0x7FFFFFFF;
    }

    /**
     * Map a battlepay_product row into the store_items shape expected by the views.
     */
    private function mapBattlePayProduct(array $row, $realm): array
    {
        return [
            'id'                        => (int) $row['id'],
            'itemid'                    => '',
            'itemcount'                 => '',
            'name'                      => $row['name'],
            'quality'                   => (int) $this->config->item('battlepay_default_quality'),
            'vp_price'                  => 0,
            'dp_price'                  => (int) $row['price'],
            'realm'                     => $realm,
            'description'               => $row['description'],
            'icon'                      => (!empty($row['icon']) ? $row['icon'] : $this->config->item('battlepay_default_icon')),
            'group'                     => $this->categoryGroupId($row['category']),
            'query'                     => '',
            'query_database'            => '',
            'query_need_character'      => 0,
            'command'                   => '',
            'command_need_character'    => 0,
            'require_character_offline' => 0,
            'tooltip'                   => 0,
            'battlepay'                 => 1,
            'delivery_command'          => $row['delivery_command'] ?? '',
        ];
    }

    public function getItems($realm)
    {
        if ($this->useBattlePay()) {
            $db = $this->battlePayDb();
            $query = $db->query("SELECT id, name, description, category, icon, price, enabled FROM battlepay_product WHERE enabled = 1 ORDER BY category ASC, id ASC");

            if ($query->getNumRows() > 0) {
                $items = [];
                foreach ($query->getResultArray() as $row) {
                    $items[] = $this->mapBattlePayProduct($row, $realm);
                }
                return $items;
            }
            return false;
        }

        $query = $this->db->query("SELECT DISTINCT store_items.*
									FROM store_items
									INNER JOIN store_groups ON store_items.group = store_groups.id
									WHERE store_items.realm = ?
									GROUP BY store_items.id
									ORDER BY store_groups.orderNumber ASC, store_items.group ASC, store_items.id ASC;", [$realm]);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        } else {
            return false;
        }
    }

    public function getItem($id)
    {
        if ($this->useBattlePay()) {
            $db = $this->battlePayDb();
            $query = $db->table('battlepay_product')->select('id, name, description, category, icon, delivery_command, price, enabled')->where(['id' => $id])->get();

            if ($query->getNumRows() > 0) {
                return $this->mapBattlePayProduct($query->getResultArray()[0], $this->config->item('default_realm') ?: 1);
            }
            return false;
        }

        $query = $this->db->table('store_items')->select()->where(['id' => $id])->orderBy('group', 'ASC')->get();

        if ($query->getNumRows() > 0) {
            $result = $query->getResultArray();

            return $result[0];
        } else {
            return false;
        }
    }

    public function getStoreGroups(): false|array
    {
        if ($this->useBattlePay()) {
            $db = $this->battlePayDb();
            $query = $db->query("SELECT DISTINCT category FROM battlepay_product WHERE enabled = 1 ORDER BY category ASC");

            if ($query->getNumRows() > 0) {
                $groups = [];
                $order = 0;
                foreach ($query->getResultArray() as $row) {
                    $groups[] = [
                        'id'          => $this->categoryGroupId($row['category']),
                        'title'       => $row['category'],
                        'icon'        => '',
                        'orderNumber' => $order++,
                    ];
                }
                return $groups;
            }
            return false;
        }

        $query = $this->db->table('store_groups')->select()->get();

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        } else {
            return false;
        }
    }

    public function logOrder($vp, $dp, $cart): void
    {
        $data = [
            'vp_cost'   => $vp,
            'dp_cost'   => $dp,
            'cart'      => json_encode($cart),
            'completed' => 0,
            'user_id'   => $this->user->getId(),
            'timestamp' => time()
        ];

        $this->db->table('order_log')->insert($data);
    }

    public function completeOrder(): void
    {
        $this->db->query("UPDATE order_log SET completed = '1' WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$this->user->getId()]);
    }

    public function getOrders($completed): array|false
    {
        if ($completed) {
            $query = $this->db->query("SELECT * FROM order_log WHERE completed = ? ORDER BY id DESC LIMIT 10", [$completed]);
        } else {
            $query = $this->db->query("SELECT * FROM order_log WHERE completed = ? AND `timestamp` > ? ORDER BY id DESC", [$completed, time() - 60 * 60 * 24 * 7]);
        }

        if ($query->getNumRows()) {
            return $query->getResultArray();
        } else {
            return false;
        }
    }

    public function getOrder($id)
    {
        $query = $this->db->query("SELECT * FROM order_log WHERE id = ?", [$id]);

        if ($query->getNumRows()) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    public function findByUserId($type, $string): array|false
    {
        $query = $this->db->query("SELECT * FROM order_log WHERE `user_id` = ? AND `completed` = ?", [$string, $type]);

        if ($query->getNumRows()) {
            return $query->getResultArray();
        } else {
            return false;
        }
    }

    public function refund($user_id, $vp, $dp): void
    {
        $this->db->query("UPDATE account_data SET vp = vp + ?, dp = dp + ? WHERE id = ?", [$vp, $dp, $user_id]);
    }

    public function deleteLog($id): void
    {
        $this->db->query("DELETE FROM order_log WHERE id = ?", [$id]);
    }
}
