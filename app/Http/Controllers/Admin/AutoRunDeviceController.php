<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Libraries\Functions;
use App\Libraries\Verify;
use DB;


class AutoRunDeviceController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('autorun_device')
               ->orderBy('id', 'desc');
        if ($request->filled('dev_id')) {
            $data->where('dev_id', 'like', '%' . $request->input('dev_id') . '%');
        }
        if ($request->filled('enable')) {
            $data->where('enable', $request->input('enable'));
        }

        $data = $data->paginate($request->input('perpage', 20));

        // 绑定U盾数量
        $id_list = $data->getCollection()->pluck('id');
        $portCount = DB::table('usb_key')
            ->selectRaw('autorun_id, COUNT(id) as total')
            ->where('autorun_id', '!=', 0)
            ->whereIn('autorun_id', $id_list)
            ->groupBy('autorun_id')
            ->get()
            ->keyBy('autorun_id');

        // 运作中U盾数量
        $activeUsbCount = DB::table('usb_key AS uk')
            ->selectRaw('uk.autorun_id, COUNT(uk.id) as total')
            ->join('usb_port AS up', 'up.usb_uid', '=', 'uk.usb_uid')
            ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
            ->whereNotNull('ud.account_id')
            ->where('uk.autorun_id', '!=', 0)
            ->whereIn('uk.autorun_id', $id_list)
            ->groupBy('uk.autorun_id')
            ->get()
            ->keyBy('autorun_id');

        // 未完成任务数量
        $dev_id_list = $data->getCollection()->pluck('dev_id');
        $undoCount = DB::table('autorun_job')
            ->selectRaw('dev_id, COUNT(id) as total')
            ->where('status', 0)
            ->where('dev_id', '!=', '無配置')
            ->whereIn('dev_id', $dev_id_list)
            ->groupBy('dev_id')
            ->get()
            ->keyBy('dev_id');

        $data->getCollection()->transform(function ($value) use ($undoCount, $portCount, $activeUsbCount) {
            $value->undoCount = isset($undoCount[$value->dev_id]) ? $undoCount[$value->dev_id]->total : 0;
            $value->portCount = isset($portCount[$value->id]) ? $portCount[$value->id]->total : 0;
            $value->activeUsbCount = isset($activeUsbCount[$value->id]) ? $activeUsbCount[$value->id]->total : 0;
            return $value;
        });

        return $this->presenter->json($data, "成功", 1);
    }

    public function getListForSelect(Request $request)
    {
        $data = DB::table('autorun_device')
            ->select(['id', 'dev_id', 'id AS value', 'dev_id AS name'])
            ->where('enable', 1)
            ->orderBy('id', 'desc')
            ->get();

        return $this->presenter->json($data, "成功", 1);
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'dev_id' => 'required',
            'operating_pw' => 'required',
        ]);

        if($request->input('operating_pw') != config('admin.operating_pw')) {
            return $this->presenter->json([], "操作密码错误", 2);
        }

        if ($this->checkExist($request->input('dev_id'))) {
            return $this->presenter->json([],'autorun設備ID不能重复',2);
        }

        $last_id = DB::table('autorun_device')
            ->insertGetId([
                'dev_id' => $request->input('dev_id')
            ]);

        if (!$last_id) {
            return $this->presenter->json([], '新增失敗', 2);
        }
        return $this->presenter->json($last_id, '新增成功', 1);
    }

    public function update(Request $request)
    {
        // autorun_device 更改 dev_id 会影响 autorun_job，此功能目前无需求，先不实作 20190422
        /*
        $this->validate($request, [
            'id' => 'required',
            'dev_id' => 'required',
            'operating_pw' => 'required',
        ]);

        if($request->input('operating_pw') != config('admin.operating_pw')) {
            return $this->presenter->json([], "操作密码错误", 2);
        }

        $input_data = $request->input();

        if ($this->checkExist($input_data['dev_id'], $input_data['id'])) {
            return $this->presenter->json([],'autorun設備ID不能重复',2);
        }

        $result = DB::table('autorun_device')
            ->where(['id' => $input_data['id']])
            ->update([
                'dev_id' => $input_data['dev_id'],
                'updated_at' => DB::raw('NOW()')
            ]);

        if (!$result) {
            return $this->presenter->json([], '更新失敗', 2);
        }*/

        return $this->presenter->json([], '更新成功', 1);
    }

    public function updateStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'enable' => [
                'required',
                Rule::in([0, 1]),
            ],
        ]);

        $result = DB::table('autorun_device')
            ->where(['id' => $request->input('id')])
            ->update(['enable' => $request->input('enable')]);

        if (!$result) {
            return $this->presenter->json([], '更新失敗，资料已更动', 2);
        }

        return $this->presenter->json([], '更新成功', 1);
    }

    // 检查名称是否重复
    private function checkExist($dev_id, $id=0)
    {
        $data = DB::table('autorun_device')
            ->select(['id'])
            ->where('dev_id', $dev_id)
            ->where('id', '!=', $id)
            ->first();
        if ($data) {
            return true;
        }
        return false;
    }
}