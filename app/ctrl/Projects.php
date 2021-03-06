<?php

namespace main\app\ctrl;

use main\app\classes\PermissionGlobal;
use main\app\classes\PermissionLogic;
use main\app\classes\ProjectLogic;
use main\app\classes\UserAuth;
use main\app\model\OrgModel;
use main\app\model\project\ProjectModel;
use main\app\classes\UserLogic;
use main\app\classes\SettingsLogic;
use main\app\classes\ConfigLogic;
use main\app\model\project\ProjectUserRoleModel;
use main\app\model\user\UserModel;
use main\app\classes\UploadLogic;
use main\lib\MySqlDump;

/**
 * 项目列表
 * Class Projects
 * @package main\app\ctrl
 */
class Projects extends BaseUserCtrl
{

    /**
     * Projects constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        parent::addGVar('top_menu_active', 'project');
    }

    /**
     * @throws \Exception
     */
    public function pageIndex()
    {
        $data = [];
        $data['title'] = '项目';
        $data['sub_nav_active'] = 'project';

        $dataKey = array(
            'count',
            'display_name'
        );

        $outProjectTypeList = [];
        $projectTypeAndCount = ProjectLogic::getAllProjectTypeTotal();

        foreach ($projectTypeAndCount as $key => $value) {
            switch ($key) {
                case 'WHOLE':
                    $outProjectTypeList[0] = array_combine($dataKey, [$value, '全部']);
                    break;
                case 'SCRUM':
                    $outProjectTypeList[ProjectLogic::PROJECT_TYPE_SCRUM] =
                        array_combine($dataKey, [$value, ProjectLogic::$typeAll[ProjectLogic::PROJECT_TYPE_SCRUM]]);
                    break;
                case 'SOFTWARE_DEV':
                    $outProjectTypeList[ProjectLogic::PROJECT_TYPE_SOFTWARE_DEV] =
                        array_combine($dataKey, [$value, ProjectLogic::$typeAll[ProjectLogic::PROJECT_TYPE_SOFTWARE_DEV]]);
                    break;
                case 'TASK_MANAGE':
                    $outProjectTypeList[ProjectLogic::PROJECT_TYPE_TASK_MANAGE] =
                        array_combine($dataKey, [$value, ProjectLogic::$typeAll[ProjectLogic::PROJECT_TYPE_TASK_MANAGE]]);
                    break;
            }
        }

        $data['type_list'] = $outProjectTypeList;
        ConfigLogic::getAllConfigs($data);
        $this->render('gitlab/project/main.php', $data);
    }

    /**
     * @param int $typeId
     * @throws \Exception
     */
    public function fetchAll($typeId = 0)
    {
        $userId = UserAuth::getId();
        $typeId = intval($typeId);
        $isAdmin = false;

        $projectIdArr = PermissionLogic::getUserRelationProjectIdArr($userId);

        $projectModel = new ProjectModel();
        if ($typeId) {
            $projects = $projectModel->filterByType($typeId, false);
        } else {
            $projects = $projectModel->getAll(false);
        }

        if (PermissionGlobal::check($userId, PermissionGlobal::ADMINISTRATOR)) {
            $isAdmin = true;
        }

        $types = ProjectLogic::$typeAll;
        foreach ($projects as $key => &$item) {
            $item['type_name'] = isset($types[$item['type']]) ? $types[$item['type']] : '--';
            $item['path'] = empty($item['org_path']) ? 'default' : $item['org_path'];
            $item['create_time_text'] = format_unix_time($item['create_time'], time());
            $item['create_time_origin'] = format_unix_time($item['create_time'], 0, 'full_datetime_format');

            $item['first_word'] = mb_substr(ucfirst($item['name']), 0, 1, 'utf-8');
            $item['bg_color'] = mapKeyColor($item['key']);
            list($item['avatar'], $item['avatar_exist']) = ProjectLogic::formatAvatar($item['avatar']);

            // 剔除没有访问权限的项目
            if (!$isAdmin && !in_array($item['id'], $projectIdArr)) {
                unset($projects[$key]);
            }
        }

        $userLogic = new UserLogic();
        $data['users'] = $userLogic->getAllNormalUser();
        unset($userLogic, $item);

        $projects = array_values($projects);
        $data['projects'] = $projects;

        $this->ajaxSuccess('success', $data);
    }

    /**
     * 项目的上传文件接口
     * @throws \Exception
     */
    public function upload()
    {
        $uuid = '';
        if (isset($_REQUEST['qquuid'])) {
            $uuid = $_REQUEST['qquuid'];
        }

        $originName = '';
        if (isset($_REQUEST['qqfilename'])) {
            $originName = $_REQUEST['qqfilename'];
        }

        $fileSize = 0;
        if (isset($_REQUEST['qqtotalfilesize'])) {
            $fileSize = (int)$_REQUEST['qqtotalfilesize'];
        }

        $uploadLogic = new UploadLogic();
        $ret = $uploadLogic->move('qqfile', 'avatar', $uuid, $originName, $fileSize);
        header('Content-type: application/json; charset=UTF-8');

        $resp = [];
        if ($ret['error'] == 0) {
            $resp['success'] = true;
            $resp['error'] = '';
            $resp['url'] = $ret['url'];
            $resp['filename'] = $ret['filename'];
            $resp['relate_path'] = $ret['relate_path'];
        } else {
            $resp['success'] = false;
            $resp['error'] = $ret['message'];
            $resp['error_code'] = $ret['error'];
            $resp['url'] = $ret['url'];
            $resp['filename'] = $ret['filename'];
        }
        echo json_encode($resp);
        exit;
    }


    public function test()
    {
        echo (new SettingsLogic)->dateTimezone();
    }


    /**
     * 初始化项目角色
     * @throws \Exception
     */
    public function initRole()
    {
        $ret = [];
        $projectArr = (new ProjectModel())->getAll(false);
        foreach ($projectArr as $item) {
            $ret[] = ProjectLogic::initRole($item['id']);
        }
        $this->ajaxSuccess('ok', $ret);
    }
}
