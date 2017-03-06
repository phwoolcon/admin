<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Model;

/**
 * Class OpLog
 * @package Phwoolcon\Admin\Model
 *
 * @property Admin $admin
 * @method Admin getAdmin()
 */
class OpLog extends Model
{
    protected $_table = 'admin_op_log';
    protected $_useDistributedId = false;

    public function initialize()
    {
        parent::initialize();
        $this->hasOne('admin_id', Admin::class, 'id', ['alias' => 'admin']);
    }

    public static function add($adminId, $action, $description = '', $result = '')
    {
        $log = new static();
        $log->addData([
            'admin_id' => $adminId,
            'action' => $action,
            'description' => $description,
            'result' => $result,
        ]);
        return $log->save();
    }
}
