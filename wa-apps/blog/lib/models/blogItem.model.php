<?php
abstract class blogItemModel extends waModel
{
    protected $where_conditions;
    protected $sql_params = array();
    protected $extend_options = array();
    protected $extend_data = array();
    private $limit = 10;
    private $search_count;

    protected $obligatory_fields = array();

    public function checkUrl($url, $id = null) {

        $url = preg_replace('/[\?#\/\s_]+/','_', $url);

        $where = array("(url='{$this->escape($url)}')");
        if ($id) {
            $where[] = "({$this->id} != {$this->escape($id)})";
        }

        return $this->select('id')->where(implode(' AND ',$where))->fetch();
    }

    public function search($options = array(),$extend_options = array(),$extend_data = array())
    {
        $this->sql_params = array();
        $this->extend_options = (array)$extend_options;
        $this->extend_data = $extend_data;
    }

    protected function buildSearchSQL($fields = array(), $count = true)
    {
        $join = '';
        $select_fields = $this->setFields($fields);
        if (isset($this->sql_params['join'])) {
            foreach ($this->sql_params['join'] as $table=>$options) {
                if (is_array($options['condition'])) {
                    $options['condition'] = implode(' AND ',$options['condition']);
                }
                $type = isset($options['type'])?strtoupper($options['type']).' ':'';
                $join .= "\n{$type}JOIN {$table} ON {$options['condition']}";

                if (isset($options['fields']) && $options['fields']) {
                    if (is_array($options['fields'])) {
                        foreach ($options['fields'] as $field=>$alias) {
                            if (is_int($field)) {
                                $field = $alias;
                            }
                            $select_fields[] = "{$table}.{$field} AS '{$alias}'";
                        }
                    } else {
                        $select_fields[] = "{$table}.*";
                    }
                }

                if (isset($options['values']) && $options['values']) {
                    if (is_array($options['values'])) {
                        foreach ($options['values'] as $alias => $value) {
                            $select_fields[] = $this->escape($value)." AS '{$alias}'";
                        }
                    } else {
                        $select_fields[] = "{$table}.*";
                    }
                }

            }
        }

        $sql = "SELECT ".($join?'DISTINCT ':'').($count?'SQL_CALC_FOUND_ROWS ':'').implode(', ',$select_fields)."\n FROM {$this->table} {$join}";
        if (isset($this->sql_params['where'])) {
            $where = implode(' AND ',$this->sql_params['where']);
            if ($where) {
                $sql .= "\n WHERE {$where}";
            }
            unset($this->sql_params['where']);
        }

        if (isset($this->sql_params['order']) && $this->sql_params['order']) {
            $sql .= "\n ORDER BY {$this->sql_params['order']}";
            unset($this->sql_params['order']);
        }
        return $sql;

    }

    protected function setFields($fields = array(), $add_table = true)
    {
        $select_fields = array();
        if ($fields) {
            if (!is_array($fields)) {
                $fields = array_map('trim',explode(',',$fields));
            }
            $fields[] = $this->id;
            $fields = array_unique($fields);
            foreach ($fields as $id => $field) {
                if (is_int($id)) {
                    $select_fields[$field] = $add_table?"{$this->table}.{$field}":$field;
                } else {
                    if(strpos($field,'.') === false) {
                        $field = $add_table?"{$this->table}.{$field}":$field;
                    }
                    $select_fields[$id] = "{$field} as '{$id}'";
                }
            }
        } elseif ($fields === false) {
            $select_fields = array();
            foreach ($this->fields as $field => $info) {
                if (!in_array(strtolower($info['type']), array('blob','text'))) {
                    $select_fields[$field] = $add_table?"{$this->table}.{$field}":$field;
                }
            }
        } else {
            $select_fields[] = $add_table?"{$this->table}.*":'*';
        }
        return $select_fields;
    }

    abstract public function prepareView($items, $options = array(), $extend_data = array());

    public function fetchSearchAll($fields = array())
    {
        $sql = $this->buildSearchSQL($fields, false);
        $items = $this->query($sql,$this->sql_params)->fetchAll('id');
        $this->search_count = count($items);
        return $this->prepareView($items);
    }

    public function fetchSearchItem($fields = array())
    {
        $sql = $this->buildSearchSQL($fields, false);
        $sql .= "\n LIMIT 1";
        $items = $this->query($sql,$this->sql_params)->fetchAll('id');

        $this->search_count = count($items);

        $items = $this->prepareView($items);
        reset($items);
        return current($items);
    }

    public function fetchSearchPage($page, $item_per_page = 10, $fields = array())
    {
        $this->limit = max(1,$item_per_page);
        $this->sql_params['offset'] = max(0,($page-1)*$this->limit);
        $this->sql_params['limit'] = $this->limit;

        $sql = $this->buildSearchSQL($fields);
        $sql .= "\n LIMIT i:offset, i:limit";
        $items = $this->query($sql,$this->sql_params)->fetchAll('id');

        $this->search_count = $this->query("SELECT FOUND_ROWS()")->fetchField();

        return $this->prepareView($items);
    }

    public function searchCount()
    {
        return $this->search_count;
    }

    public function pageCount()
    {
        return ceil($this->search_count/$this->limit);
    }
}