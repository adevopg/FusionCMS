<?php

class Items_model extends CI_Model
{
    public function getItems()
    {
        $query = $this->db->query("SELECT i.*, g.title, g.orderNumber FROM store_items i, store_groups g WHERE g.id = i.group ORDER BY `group` ASC");

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();
        } else {
            $row = array();
        }

        $query = $this->db->query("SELECT * FROM store_items WHERE `group` = ''");

        if ($query->getNumRows() > 0) {
            $row2 = $query->getResultArray();

            return array_merge($row, $row2);
        } elseif (count($row)) {
            return $row;
        } else {
            return false;
        }
    }

    public function getGroups()
    {
        $query = $this->db->query("SELECT * FROM store_groups ORDER BY `id` ASC");

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row;
        } else {
            return false;
        }
    }

    public function add($data)
    {
        $this->db->table('store_items')->insert($data);
    }

    public function addGroup($data)
    {
        $this->db->table('store_groups')->insert($data);
    }

    public function edit($id, $data)
    {
        $this->db->table('store_items')->where('id', $id)->update($data);
    }

    public function editGroup($id, $data)
    {
        $this->db->table('store_groups')->where('id', $id)->update($data);
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM store_items WHERE id = ?", [$id]);
    }

    public function deleteGroup($id)
    {
        $this->db->query("DELETE FROM store_items WHERE `group` = ?", [$id]);
        $this->db->query("DELETE FROM store_groups WHERE id = ?", [$id]);
    }

    public function getItem($id)
    {
        $query = $this->db->query("SELECT * FROM store_items WHERE id = ? LIMIT 1", [$id]);

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    public function getGroup($id)
    {
        $query = $this->db->query("SELECT * FROM store_groups WHERE id = ? LIMIT 1", [$id]);

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    /*
    | -------------------------------------------------------------------
    |  BattlePay catalog (shared with the in-game shop, table battlepay_product
    |  on the realm account/auth database)
    | -------------------------------------------------------------------
    */

    private function bpDb()
    {
        return $this->load->database('account', true);
    }

    public function getBattlePayItems()
    {
        $query = $this->bpDb()->query("SELECT id, name, description, category, icon, price, enabled FROM battlepay_product ORDER BY category ASC, id ASC");

        return $query->getNumRows() > 0 ? $query->getResultArray() : [];
    }

    public function getBattlePayItem($id)
    {
        $query = $this->bpDb()->query("SELECT id, name, description, category, icon, delivery_command, price, enabled FROM battlepay_product WHERE id = ? LIMIT 1", [$id]);

        return $query->getNumRows() > 0 ? $query->getResultArray()[0] : false;
    }

    public function getBattlePayCategories()
    {
        $query = $this->bpDb()->query("SELECT DISTINCT category FROM battlepay_product WHERE category <> '' ORDER BY category ASC");

        $cats = [];
        foreach ($query->getResultArray() as $r) {
            $cats[] = $r['category'];
        }
        return $cats;
    }

    public function addBattlePay($data)
    {
        $this->bpDb()->table('battlepay_product')->insert($data);
    }

    public function editBattlePay($id, $data)
    {
        $this->bpDb()->table('battlepay_product')->where('id', $id)->update($data);
    }

    public function deleteBattlePay($id)
    {
        $this->bpDb()->query("DELETE FROM battlepay_product WHERE id = ?", [$id]);
    }

    public function toggleBattlePay($id)
    {
        $this->bpDb()->query("UPDATE battlepay_product SET enabled = 1 - enabled WHERE id = ?", [$id]);
    }
}
