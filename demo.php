<?php

namespace App\Services\Uniforms;

use App\Repositories\Uniforms\UniformsCategoryRepository;
use App\Repositories\Permission\PermissionLanguageRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Helpers\CommonHelper;
use DB;

class UniformsCategoryService
{
    use CommonHelper;

    protected $data;
    protected $info;
    protected $uniformsCategoryRepository;
    protected $permissionLanguageRepository;

    public function __construct(
        UniformsCategoryRepository $uniformsCategoryRepository,
        PermissionLanguageRepository $permissionLanguageRepository
    ) {
        $this->data = [];
        $this->info = [];
        $this->uniformsCategoryRepository = $uniformsCategoryRepository;
        $this->permissionLanguageRepository = $permissionLanguageRepository;
    }

    /**
     * 取得制服類別
     *
     * @param array $data
     * @return void
     */
    public function getUniformsCategoryList(array $data): array
    {
        /* 預設分頁資訊 */
        $page   = $data['page'] !== '' ? $data['page'] : config('uniformsConstant.DEFAULT_PAGE');
        $count  = $data['count'] !== '' ? $data['count'] : config('uniformsConstant.DEFAULT_RESULT');
        unset($data['page']);
        unset($data['count']);

        $offset     = ($page - 1) * $count;
        $sortCol    = $data['sort_field'] !== '' ? $data['sort_field'] : 'id';
        $sortOrder  = $data['sort_order'] !== '' ? $data['sort_order'] : 'desc';
        if ($data['is_show'] === '') {
            unset($data['is_show']);
        }
        unset($data['sort_field']);
        unset($data['sort_order']);

        $totalCount = $this->uniformsCategoryRepository->getCategoryCountAll($data);
        if (empty($totalCount)) {
            return [
                'code'  => 101,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'no_data')
            ];
        }

        $list = $this->uniformsCategoryRepository->getCategoryListLimitByCondition($data, $offset, $count, $sortCol, $sortOrder)->toArray();

        $outputData = [
            'list'         => $list,
            'total_count'  => $totalCount,
            'page'         => $page,
            'perpage'      => $count,
            'total_page'   => ceil($totalCount/$count),
        ];

        foreach ($outputData as $key => $value) {
            $this->data[$key] = $value;
            $this->info[$key] = __(config('uniformsConstant.COMMON_DEFAULT_LANG_MESSAGE').$key);
        }

        return [
            'data' => $this->data,
            'info' => $this->info
        ];
    }

    /**
     * 取得制服類別(下拉選單)
     *
     * @param array $data
     * @return void
     */
    public function getUniformsCategoryDropdown(array $data): array
    {
        /* 預設分頁資訊 */
        $page   = $data['page'] !== '' ? $data['page'] : config('uniformsConstant.DEFAULT_PAGE');
        $count  = $data['count'] !== '' ? $data['count'] : config('uniformsConstant.DEFAULT_RESULT');
        unset($data['page']);
        unset($data['count']);

        $offset     = ($page - 1) * $count;
        $sortCol    = $data['sort_field'] !== '' ? $data['sort_field'] : 'id';
        $sortOrder  = $data['sort_order'] !== '' ? $data['sort_order'] : 'desc';
        if ($data['is_show'] === '') {
            unset($data['is_show']);
        }
        $login_language = $data['login_language'];
        unset($data['login_language']);
        unset($data['sort_field']);
        unset($data['sort_order']);

        $totalCount = $this->uniformsCategoryRepository->getCategoryCountAll($data);
        if (empty($totalCount)) {
            return [
                'code'  => 101,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'no_data')
            ];
        }

        $list = $this->uniformsCategoryRepository->getCategoryListLimitByCondition($data, $offset, $count, $sortCol, $sortOrder)->toArray();
        $list = $this->listToDropdownCommonHelper($list, $login_language);

        $outputData = [
            'list'         => $list,
            'total_count'  => $totalCount,
            'page'         => $page,
            'perpage'      => $count,
            'total_page'   => ceil($totalCount/$count),
        ];

        foreach ($outputData as $key => $value) {
            $this->data[$key] = $value;
            $this->info[$key] = __(config('uniformsConstant.COMMON_DEFAULT_LANG_MESSAGE').$key);
        }

        return [
            'data' => $this->data,
            'info' => $this->info
        ];
    }

    /**
     * 建立制服類別
     *
     * @param array $data
     * @return void
     */
    public function createUniformsCategory(array $data): array
    {
        if ($data['have_steel'] === '') {
            unset($data['have_steel']);
        }
        $totalCount = $this->uniformsCategoryRepository->getCategoryCountAll($data);
        if ($totalCount > 1) {
            return [
                'code'  => 102,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'same_name')
            ];
        }

        DB::beginTransaction();
        $createLangResult = $this->permissionLanguageRepository->createPermissionLanguage(['cn_name' => 'cn'.$data['ch_name']]);
        if (empty($createLangResult)) {
            DB::rollback();
            return [
                'code'  => 103,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'create_fail')
            ];
        }
        $data['language_id'] = $createLangResult->id;


        // 建立資料
        $createResult = $this->uniformsCategoryRepository->createCategory($data);
        if (empty($createResult)) {
            DB::rollback();
            return [
                'code'  => 104,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'create_fail')
            ];
        }
        DB::commit();

        return [
            'data' => $this->data,
            'info' => $this->info
        ];
    }

    /**
     * 更新制服類別
     *
     * @param [type] $data
     * @return void
     */
    public function updateUniformsCategory(array $data): array
    {
        $id = $data['id'];
        unset($data['id']);
        if ($data['is_show'] === '') {
            unset($data['is_show']);
        }
        if ($data['have_steel'] === '') {
            unset($data['have_steel']);
        }

        $totalCount = $this->uniformsCategoryRepository->getCategoryCountAll(['id' => $id]);
        if (empty($totalCount)) {
            return [
                'code'  => 104,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'no_data')
            ];
        }

        $updateResult = $this->uniformsCategoryRepository->updateCategory($id, $data);
        if (!$updateResult) {
            return [
                'code'  => 105,
                'msg'   => __(config('uniformsConstant.DEFAULT_LANG_MESSAGE').'update_fail')
            ];
        }

        return [
            'data' => $this->data,
            'info' => $this->info
        ];
    }
}
