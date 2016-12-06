<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Model;

class OpLog extends Model
{
    protected $_table = 'admin_op_log';
    protected $_useDistributedId = false;

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
