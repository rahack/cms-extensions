<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class Upage_Data_CategoryMapper
 */
class Upage_Data_CategoryMapper extends Upage_Data_Mapper
{
    /**
     * Upage_Data_CategoryMapper constructor.
     */
    public function __construct()
    {
        parent::__construct('Category', 'categories', 'id');
    }

    /**
     * Find category row by filter
     *
     * @param array $filter Filter parameters
     *
     * @return array|void
     */
    public function find($filter = array())
    {
        $where = array();
        if (isset($filter['id'])) {
            $where[] = 'id = ' . intval($filter['id']);
        }
        if (isset($filter['extension'])) {
            $where[] = 'extension = ' . $this->_db->Quote($filter['extension']);
        }
        if (isset($filter['title'])) {
            $where[] = 'title = ' . $this->_db->Quote($filter['title']);
        }

        $result = $this->_loadObjects($where, isset($filter['limit']) ? (int)$filter['limit'] : 0, true);
        return $result;
    }

    /**
     * Create raw category object
     *
     * @return bool|JTable
     */
    public function create()
    {
        $row = $this->_create();
        $row->setLocation(1, 'last-child');
        $row->published = 1;
        $row->params = '{"category_layout":"","image":""}';
        $row->metadata = '{"author":"","robots":""}';
        $row->language = '*';
        return $row;
    }

    /**
     * Delete category object by id
     *
     * @param int $id Category id
     *
     * @return null|void
     */
    public function delete($id)
    {
        $status = $this->_cascadeDelete('content', array('category' => $id));
        if (is_string($status)) {
            return $this->_error($status, 1);
        }
        return parent::delete($id);
    }

    /**
     * Method to save category object
     *
     * @param object $category Category object
     *
     * @return null|void
     */
    public function save($category)
    {
        $status = parent::save($category);
        if (is_string($status)) {
            return $this->_error($status, 1);
        }
        if (!$category->rebuildPath($category->id)) {
            return $this->_error($category->getError(), 1);
        }
        if (!$category->rebuild($category->id, $category->lft, $category->level, $category->path)) {
            return $this->_error($category->getError(), 1);
        }
        return null;
    }
}

/**
 * Class Upage_Data_ContentMapper
 */
class Upage_Data_ContentMapper extends Upage_Data_Mapper
{
    /**
     * Upage_Data_ContentMapper constructor.
     */
    function __construct()
    {
        parent::__construct('content', 'content', 'id');
    }

    /**
     * Method to find content rows by filter
     *
     * @param array $filter Filter parameters
     *
     * @return array|void
     */
    function find($filter = array())
    {
        $where = array();
        if (isset($filter['id'])) {
            $where[] = 'id = ' . intval($filter['id']);
        }
        if (isset($filter['section'])) {
            $where[] = 'sectionid = ' . intval($filter['section']);
        }
        if (isset($filter['category'])) {
            $where[] = 'catid = ' . intval($filter['category']);
        }
        if (isset($filter['title'])) {
            $where[] = 'title = ' . $this->_db->Quote($this->_db->escape($filter['title'], true), false);
        }
        $result = $this->_loadObjects($where, isset($filter['limit']) ? (int)$filter['limit'] : 0);
        return $result;
    }

    /**
     * Method to create raw content row
     *
     * @return bool|JTable
     */
    function create()
    {
        $row = $this->_create();
        $row->state = '1';
        $row->version = '1';
        $row->language = '*';
        $row->created = JFactory::getDate()->toSql();
        $row->publish_up = $row->created;
        $row->publish_down = $this->_db->getNullDate();
        return $row;
    }

    /**
     * Method to save row object
     *
     * @param object $row Row object
     *
     * @return null|void
     */
    function save($row)
    {
        JPluginHelper::importPlugin('content');

        $isNew = (bool)$row->id;
        if (!$row->check()) {
            return $this->_error($row->getError(), 1);
        }
        $dispatcher = JDispatcher::getInstance();
        $result = $dispatcher->trigger('onBeforeContentSave', array($row, $isNew));
        if (in_array(false, $result, true)) {
            return $this->_error($row->getError(), 1);
        }
        if (!$row->store()) {
            return $this->_error($row->getError(), 1);
        }
        $row->checkin();
        $row->reorder('catid = ' . (int)$row->catid . ' AND state >= 0');
        $cache = JFactory::getCache('com_content');
        $cache->clean();
        $dispatcher->trigger('onAfterContentSave', array($row, $isNew));
        return null;
    }
}

/**
 * Class Upage_Data_ExtensionMapper
 */
class Upage_Data_ExtensionMapper extends Upage_Data_Mapper
{
    /**
     * Upage_Data_ExtensionMapper constructor.
     */
    function __construct()
    {
        parent::__construct('Extension', 'extensions', 'extension_id');
    }

    /**
     * Method to find extension rows by filter
     *
     * @param array $filter Filter parameters
     *
     * @return array|void
     */
    function find($filter = array())
    {
        $where = array();
        if (isset($filter['element'])) {
            $where[] = 'element = ' . $this->_db->Quote($this->_db->escape($filter['element'], true), false);
        }
        $result = $this->_loadObjects($where, isset($filter['limit']) ? (int)$filter['limit'] : 0);
        return $result;
    }

    /**
     * Method to create raw extension row
     *
     * @return bool|JTable
     */
    function create()
    {
        $row = $this->_create();
        return $row;
    }
}

/**
 * Class Upage_Data_Mapper
 */
class Upage_Data_Mapper
{
    /**
     * @var JDatabaseDriver
     */
    protected $_db;

    /**
     * @var
     */
    protected $_entity;

    /**
     * @var Table name from db
     */
    protected $_table;

    /**
     * @var Table primary key
     */
    protected $_pk;

    /**
     * Upage_Data_Mapper constructor.
     *
     * @param string $entity Entity table
     * @param string $table  Table name
     * @param string $pk     Primary key value
     */
    public function __construct($entity, $table, $pk)
    {
        $this->_entity = $entity;
        $this->_table = $table;
        $this->_pk = $pk;
        $this->_db = JFactory::getDBO();
    }

    /**
     * Check rows exists by filter
     *
     * @param array $filter Filter parameters
     *
     * @return bool|void
     */
    public function exists($filter = array())
    {
        $row = $this->findOne($filter);
        if (is_string($row)) {
            return $this->_error($row, 1);
        }
        return !is_null($row);
    }

    /**
     * Method to get one row by filter
     *
     * @param array $filter Filter parameters
     *
     * @return mixed|null|void
     */
    public function findOne($filter = array())
    {
        $filter['limit'] = 1;
        $list = $this->find($filter);
        if (is_string($list)) {
            return $this->_error($list, 1);
        }
        if (0 == count($list)) {
            $null = null;
            return $null;
        }
        return $list[0];
    }

    /**
     * Method to find results by filter
     *
     * @param array $filter Filter parameters
     *
     * @return array|void
     */
    public function find($filter = array())
    {
        $result = $this->_loadObjects();
        return $result;
    }

    /**
     * Method to fetch row by id
     *
     * @param int $id Row id
     *
     * @return bool|JTable
     */
    public function fetch($id)
    {
        $row = JTable::getInstance($this->_entity);
        $row->load($id);
        return $row;
    }

    /**
     * Method to delete row by id
     *
     * @param int $id Row id
     *
     * @return null|void
     */
    public function delete($id)
    {
        $row = $this->fetch($id);
        if (!$row->delete($id)) {
            return $this->_error($row->getError(), 1);
        }
        return null;
    }

    /**
     * Method to save row object
     *
     * @param object $row Row object
     *
     * @return null|void
     */
    public function save($row)
    {
        if (!$row->check()) {
            return $this->_error($row->getError(), 1);
        }
        if (!$row->store()) {
            return $this->_error($row->getError(), 1);
        }
        if (!$row->checkin()) {
            return $this->_error($row->getError(), 1);
        }
        return null;
    }

    /**
     * Method to create raw object
     *
     * @return bool|JTable
     */
    protected function _create()
    {
        $result = JTable::getInstance($this->_entity);
        return $result;
    }

    /**
     * Method to load objects by parameters
     *
     * @param array $where Custom parameters
     * @param int   $limit Count rows
     *
     * @return array|void
     */
    protected function _loadObjects($where = array(), $limit = 0)
    {
        $query = 'SELECT * FROM #__' . $this->_table
            . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . $this->_pk;
        $this->_db->setQuery($query, 0, $limit);
        $rows = $this->_db->loadAssocList();
        if ($this->_db->getErrorNum()) {
            return $this->_error($this->_db->stderr(), 1);
        }
        $result = array();
        for ($i = 0; $i < count($rows); $i++) {
            $result[$i] = JTable::getInstance($this->_entity);
            $result[$i]->bind($rows[$i]);
        }
        return $result;
    }

    /**
     * Cascading delete rows by filter
     *
     * @param string $mapper Mapper name
     * @param array  $filter Filter parameters
     *
     * @return null|void
     */
    protected function _cascadeDelete($mapper, $filter)
    {
        $menuItems = Upage_Data_Mappers::get($mapper);
        $itemsList = $menuItems->find($filter);
        if (is_string($itemsList)) {
            return $this->_error($itemsList, 1);
        }
        foreach ($itemsList as $item) {
            $status = $menuItems->delete($item->id);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
        }
        return null;
    }

    /**
     * Create Upage_Data_Mappers error
     *
     * @param string $error Error text
     * @param int    $code  Number code
     */
    protected function _error($error, $code)
    {
        Upage_Data_Mappers::error($error, $code);
    }
}

/**
 * Class Upage_Data_Mappers
 */
class Upage_Data_Mappers
{
    /**
     *  Callback error function
     *
     * @param callable $callback Callback function
     * @param bool     $get      Flag parameter
     *
     * @return mixed
     */
    public static function errorCallback($callback, $get = false)
    {
        static $errorCallback;
        if (!$get) {
            $errorCallback = $callback;
        }
        return $errorCallback;
    }

    /**
     * Method to get mapper object by name
     *
     * @param string $name Mapper name
     *
     * @return mixed
     */
    public static function get($name)
    {
        $className = 'Upage_Data_' . ucfirst($name) . 'Mapper';
        $mapper = new $className();
        return $mapper;
    }

    /**
     * Method to create error
     *
     * @param string $error Error text
     * @param int    $code  Number code
     *
     * @return mixed
     */
    public static function error($error, $code)
    {
        $null = null;
        $callback = Upage_Data_Mappers::errorCallback($null, true);
        if (isset($callback)) {
            call_user_func($callback, $error, $code);
        }
        return $error;
    }
}

/**
 * Class Upage_Data_ModuleMapper
 */
class Upage_Data_ModuleMapper extends Upage_Data_Mapper
{
    /**
     * Upage_Data_ModuleMapper constructor.
     */
    function __construct()
    {
        parent::__construct('module', 'modules', 'id');
    }

    /**
     * Method to find module rows by filter
     *
     * @param array $filter Filtering parameters
     *
     * @return array|void
     */
    function find($filter = array())
    {
        $where = array();
        if (isset($filter['published'])) {
            $where[] = 'published = ' . $this->_db->Quote($filter['published'], false);
        }
        if (isset($filter['module'])) {
            $where[] = 'module = ' . $this->_db->Quote($filter['module'], false);
        }
        if (isset($filter['position'])) {
            $where[] = 'position = ' . $this->_db->Quote($filter['position'], false);
        }
        if (isset($filter['title'])) {
            $where[] = 'title = ' . $this->_db->Quote($this->_db->escape($filter['title'], true), false);
        }
        if (isset($filter['scope']) && ('site' == $filter['scope'] || 'administrator' == $filter['scope'])) {
            $where[] = 'client_id = ' . ('site' == $filter['scope'] ? '0' : '1');
        }
        $result = $this->_loadObjects($where, isset($filter['limit']) ? (int)$filter['limit'] : 0);
        return $result;
    }

    /**
     * Method to fetch row by id
     *
     * @param int $id Row id
     *
     * @return bool|JTable
     */
    function fetch($id)
    {
        $result = parent::fetch($id);
        return $result;
    }

    /**
     * Delete module object by id
     *
     * @param int $id Module id
     *
     * @return null|void
     */
    function delete($id)
    {
        $status = $this->enableOn($id, array());
        if (is_string($status)) {
            return $status;
        }
        return parent::delete($id);
    }

    /**
     * Method to create raw module raw
     *
     * @return bool|JTable
     */
    function create()
    {
        $row = $this->_create();
        $row->published = 1;
        $row->language = '*';
        $row->showtitle = 1;
        return $row;
    }

    /**
     * Method to enable module for custom menut items
     *
     * @param int   $id    module id
     * @param array $items Array of menu items
     *
     * @return null|void
     */
    function enableOn($id, $items)
    {
        $query = 'DELETE FROM #__modules_menu WHERE moduleid = ' . $this->_db->Quote($id);
        $this->_db->setQuery($query);
        $this->_db->query();
        if ($this->_db->getErrorNum()) {
            return $this->_error($this->_db->stderr(), 1);
        }
        foreach ($items as $i) {
            $query = 'INSERT INTO #__modules_menu (moduleid, menuid) VALUES ('
                . $this->_db->Quote($id) . ',' . $this->_db->Quote($i) . ')';
            $this->_db->setQuery($query);
            $this->_db->query();
            if ($this->_db->getErrorNum()) {
                return $this->_error($this->_db->stderr(), 1);
            }
        }
        return null;
    }

    /**
     * Method to disable module for custom menut items
     *
     * @param int   $id    module id
     * @param array $items Array of menu items
     *
     * @return null|void
     */
    function disableOn($id, $items)
    {
        $query = 'DELETE FROM #__modules_menu WHERE moduleid = ' . $this->_db->Quote($id);
        $this->_db->setQuery($query);
        $this->_db->query();
        if ($this->_db->getErrorNum()) {
            return $this->_error($this->_db->stderr(), 1);
        }
        foreach ($items as $i) {
            $query = 'INSERT INTO #__modules_menu (moduleid, menuid) VALUES ('
                . $this->_db->Quote($id) . ',' . $this->_db->Quote('-' . $i) . ')';
            $this->_db->setQuery($query);
            $this->_db->query();
            if ($this->_db->getErrorNum()) {
                return $this->_error($this->_db->stderr(), 1);
            }
        }
        return null;
    }

    /**
     * Method to get assigment menut items by module id
     *
     * @param int $id
     */
    function getAssignment($id)
    {
        $query = 'SELECT menuid FROM #__modules_menu WHERE moduleid = ' . $this->_db->Quote($id);
        $this->_db->setQuery($query);
        $this->_db->query();
        $rows = $this->_db->loadColumn(0);
        if ($this->_db->getErrorNum()) {
            return $this->_error($this->_db->stderr(), 1);
        }
        return $rows;
    }
}

/**
 * Class Upage_Data_SectionMapper
 */
class Upage_Data_SectionMapper extends JTable
{
    /**
     * Upage_Data_SectionMapper constructor.
     */
    function __construct()
    {
        parent::__construct('#__upage_sections', 'id', JFactory::getDBO());
    }
}

