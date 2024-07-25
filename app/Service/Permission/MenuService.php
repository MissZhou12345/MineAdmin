<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace App\Service\Permission;

use App\Model\Permission\Menu;
use App\Repository\Permission\MenuRepository;
use Mine\Abstracts\AbstractService;
use Mine\Annotation\DependProxy;
use Mine\Interfaces\ServiceInterface\MenuServiceInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

#[DependProxy(values: [MenuServiceInterface::class])]
class MenuService extends AbstractService implements MenuServiceInterface
{
    /**
     * @var MenuRepository
     */
    public $repository;

    /**
     * MenuRepository constructor.
     */
    public function __construct(MenuRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getTreeList(?array $params = null, bool $isScope = true): array
    {
        $params = array_merge(['orderBy' => 'sort', 'orderType' => 'desc'], $params);
        return parent::getTreeList($params, $isScope);
    }

    public function getTreeListByRecycle(?array $params = null, bool $isScope = true): array
    {
        $params = array_merge(['orderBy' => 'sort', 'orderType' => 'desc'], $params);
        return parent::getTreeListByRecycle($params, $isScope);
    }

    /**
     * 获取前端选择树.
     */
    public function getSelectTree(array $data): array
    {
        return $this->repository->getSelectTree($data);
    }

    /**
     * 通过code获取菜单名称.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function findNameByCode(string $code): string
    {
        if (strlen($code) < 1) {
            return t('system.undefined_menu');
        }
        $name = $this->repository->findNameByCode($code);
        return $name ?? t('system.undefined_menu');
    }

    /**
     * 新增菜单.
     */
    public function save(array $data): mixed
    {
        $id = $this->repository->save($this->handleData($data));

        // 生成RESTFUL按钮菜单
        if ($data['type'] == Menu::MENUS_LIST && $data['restful'] == '1') {
            $model = $this->repository->model::find($id, ['id', 'name', 'code']);
            $this->genButtonMenu($model);
        }

        return $id;
    }

    /**
     * 生成按钮菜单.
     */
    public function genButtonMenu(Menu $model): bool
    {
        $buttonMenus = [
            ['name' => $model->name . '列表', 'code' => $model->code . ':index'],
            ['name' => $model->name . '回收站', 'code' => $model->code . ':recycle'],
            ['name' => $model->name . '保存', 'code' => $model->code . ':save'],
            ['name' => $model->name . '更新', 'code' => $model->code . ':update'],
            ['name' => $model->name . '删除', 'code' => $model->code . ':delete'],
            ['name' => $model->name . '读取', 'code' => $model->code . ':read'],
            ['name' => $model->name . '恢复', 'code' => $model->code . ':recovery'],
            ['name' => $model->name . '真实删除', 'code' => $model->code . ':realDelete'],
            ['name' => $model->name . '导入', 'code' => $model->code . ':import'],
            ['name' => $model->name . '导出', 'code' => $model->code . ':export'],
        ];

        foreach ($buttonMenus as $button) {
            $this->save(
                array_merge(
                    ['parent_id' => $model->id, 'type' => Menu::BUTTON],
                    $button
                )
            );
        }

        return true;
    }

    /**
     * 更新菜单.
     */
    public function update(mixed $id, array $data): bool
    {
        return $this->repository->update($id, $this->handleData($data));
    }

    /**
     * 真实删除菜单.
     */
    public function realDel(array $ids): ?array
    {
        // 跳过的菜单
        $ctuIds = [];
        if (count($ids)) {
            foreach ($ids as $id) {
                if (! $this->checkChildrenExists((int) $id)) {
                    $this->repository->realDelete([$id]);
                } else {
                    $ctuIds[] = $id;
                }
            }
        }
        return count($ctuIds) ? $this->repository->getMenuName($ctuIds) : null;
    }

    /**
     * 检查子菜单是否存在.
     */
    public function checkChildrenExists(int $id): bool
    {
        return $this->repository->checkChildrenExists($id);
    }

    /**
     * 处理数据.
     */
    protected function handleData(array $data): array
    {
        if (empty($data['parent_id']) || $data['parent_id'] == 0) {
            $data['level'] = '0';
            $data['parent_id'] = 0;
            $data['type'] = $data['type'] === Menu::BUTTON ? Menu::MENUS_LIST : $data['type'];
        } else {
            $parentMenu = $this->repository->read((int) $data['parent_id']);
            $data['level'] = $parentMenu['level'] . ',' . $parentMenu['id'];
        }
        return $data;
    }
}